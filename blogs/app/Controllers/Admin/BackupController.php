<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Cache;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Migrator;
use BlogFlow\Core\Response;
use BlogFlow\Services\BackupService;
use BlogFlow\Services\HtmlSanitizer;
use BlogFlow\Services\PageCache;
use BlogFlow\Services\PostService;
use BlogFlow\Services\Scheduler;

final class BackupController extends AdminController
{
    private function service(): BackupService
    {
        return $this->app->service(BackupService::class);
    }

    public function index(): Response
    {
        $migrator = new Migrator($this->db, BF_ROOT . '/database/migrations');
        $uploadsBytes = 0;
        $uploadsCount = 0;
        if (is_dir(BF_ROOT . '/uploads')) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BF_ROOT . '/uploads', \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $uploadsBytes += $f->getSize();
                    $uploadsCount++;
                }
            }
        }
        return $this->page('backup/index', [
            'pageTitle' => 'Backup & restore',
            'backups' => $this->service()->list(),
            'lastBackup' => $this->app->settings->get('system.last_backup_at'),
            'applied' => $migrator->applied(),
            'pending' => $migrator->pending(),
            'sections' => BackupService::CONFIG_SECTIONS,
            'uploadsBytes' => $uploadsBytes,
            'uploadsCount' => $uploadsCount,
            'zip' => class_exists(\ZipArchive::class),
            'dbVersion' => $this->db->serverVersion(),
        ]);
    }

    public function createDatabase(): Response
    {
        try {
            $name = $this->service()->createDatabaseBackup();
            $this->log('backup_create', 'backup', null, 'Created database backup ' . $name);
            $this->flash('success', 'Database backup created: ' . $name . '. Download it and keep a copy off the server.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Backup failed: ' . $e->getMessage());
        }
        return $this->redirect($this->app->url->admin('backup'));
    }

    public function download(string $file): Response
    {
        if (!BackupService::validName($file) || !is_file(BackupService::dir() . '/' . $file)) {
            throw new HttpException(404);
        }
        $this->log('backup_download', 'backup', null, 'Downloaded ' . $file);
        $path = BackupService::dir() . '/' . $file;
        // Stream without loading the whole file into memory.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . (str_ends_with($file, '.zip') ? 'application/zip' : 'application/gzip'));
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function delete(string $file): Response
    {
        if (!BackupService::validName($file)) {
            throw new HttpException(404);
        }
        @unlink(BackupService::dir() . '/' . $file);
        $this->log('backup_delete', 'backup', null, 'Deleted ' . $file);
        $this->flash('success', 'Backup deleted.');
        return $this->redirect($this->app->url->admin('backup'));
    }

    public function restore(): Response
    {
        $back = $this->app->url->admin('backup');
        $user = $this->db->one('SELECT password_hash FROM {{users}} WHERE id = ?', [$this->app->auth->id()]);
        if (!password_verify((string) ($this->request->post['password'] ?? ''), (string) $user['password_hash'])) {
            $this->flash('error', 'Your password was incorrect. Nothing was restored.');
            return $this->redirect($back);
        }
        if (trim((string) ($this->request->post['confirm'] ?? '')) !== 'RESTORE') {
            $this->flash('error', 'Type RESTORE to confirm. Nothing was restored.');
            return $this->redirect($back);
        }
        $file = $this->request->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $this->flash('error', 'Upload a .sql.gz backup created by BlogFlow.');
            return $this->redirect($back);
        }
        $actor = $this->app->auth->user();
        try {
            $result = $this->service()->restore($file['tmp_name']);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect($back);
        }
        $this->db->insert('activity_logs', ['user_id' => null, 'action' => 'backup_restore', 'entity_type' => 'backup', 'summary' => 'Database restored by ' . $actor['email'] . ' (' . $result['statements'] . ' statements). Safety backup: ' . $result['safety'], 'ip' => $this->request->ip(), 'created_at' => gmdate('Y-m-d H:i:s')]);
        // The users table may have changed: end the session and ask to sign in again.
        $this->app->auth->logout();
        $this->flash('success', 'Database restored. A safety copy of the previous data was saved as ' . $result['safety'] . '. Please sign in again.');
        return $this->redirect($this->app->url->admin('login'));
    }

    public function media(): Response
    {
        try {
            $name = $this->service()->createMediaArchive();
            $this->log('backup_media', 'backup', null, 'Created media archive ' . $name);
            $this->flash('success', 'Media archive created: ' . $name . '.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect($this->app->url->admin('backup'));
    }

    public function exportSettings(): Response
    {
        $sections = array_values(array_intersect(array_keys(BackupService::CONFIG_SECTIONS), (array) ($this->request->post['sections'] ?? [])));
        if (!$sections) {
            $this->flash('error', 'Choose at least one section to export.');
            return $this->redirect($this->app->url->admin('backup'));
        }
        $data = $this->service()->exportConfig($sections);
        $this->log('config_export', 'settings', null, 'Exported configuration: ' . implode(', ', $sections));
        return new Response((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="blogflow-config-' . gmdate('Y-m-d') . '.json"',
        ]);
    }

    public function importSettings(): Response
    {
        $back = $this->app->url->admin('backup');
        $file = $this->request->file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 2 * 1024 * 1024) {
            $this->flash('error', 'Upload a configuration JSON file (max 2 MB).');
            return $this->redirect($back);
        }
        $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        $sections = array_values(array_intersect(array_keys(BackupService::CONFIG_SECTIONS), (array) ($this->request->post['sections'] ?? [])));
        try {
            $r = $this->service()->importConfig(is_array($data) ? $data : [], $sections);
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect($back);
        }
        $this->log('config_import', 'settings', null, 'Imported configuration: ' . implode(', ', $r['imported']));
        if ($r['imported']) {
            $this->flash('success', 'Imported: ' . implode(', ', $r['imported']) . '.');
        }
        foreach ($r['errors'] as $err) {
            $this->flash('error', $err);
        }
        if (!$r['imported'] && !$r['errors']) {
            $this->flash('info', 'Nothing matching the selected sections was found in the file.');
        }
        return $this->redirect($back);
    }

    public function maintenance(string $action): Response
    {
        $back = $this->app->url->admin('backup');
        switch ($action) {
            case 'clear-cache':
                $n = $this->app->cache->flush();
                PageCache::clear();
                Cache::removeTree(BF_ROOT . '/storage/cache/purifier', false);
                $msg = "Caches cleared ($n files).";
                break;
            case 'migrate':
                $done = (new Migrator($this->db, BF_ROOT . '/database/migrations'))->migrate();
                $this->app->cache->flush();
                $msg = $done ? 'Applied migrations: ' . implode(', ', $done) . '.' : 'The database is up to date.';
                break;
            case 'publish-scheduled':
                @unlink(BF_ROOT . '/storage/cache/next-publish.txt');
                $n = Scheduler::tick($this->db);
                $msg = "$n scheduled post(s) published.";
                break;
            case 'rebuild-stats':
                $service = $this->app->service(PostService::class);
                $n = 0;
                foreach ($this->db->all('SELECT id, content_html, featured_image_id, og_image_id FROM {{posts}}') as $p) {
                    $text = HtmlSanitizer::text((string) $p['content_html']);
                    $words = HtmlSanitizer::wordCount($text);
                    $this->db->update('posts', ['content_text' => $text, 'word_count' => $words, 'reading_time' => HtmlSanitizer::readingTime($words)], 'id = ?', [$p['id']]);
                    $service->syncMedia((int) $p['id'], (string) $p['content_html'], [$p['featured_image_id'], $p['og_image_id']]);
                    $n++;
                }
                PageCache::clear();
                $msg = "Recalculated word counts, search text and media usage for $n post(s).";
                break;
            case 'prune-logs':
                $n = $this->db->delete('activity_logs', 'created_at < ?', [gmdate('Y-m-d H:i:s', time() - 180 * 86400)]);
                $n += $this->db->delete('login_attempts', 'created_at < ?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
                $n += $this->db->delete('password_reset_tokens', 'expires_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
                $msg = "Removed $n old log, login-attempt and token record(s).";
                break;
            default:
                throw new HttpException(404);
        }
        $this->log('maintenance', 'system', null, $msg);
        $this->flash('success', $msg);
        return $this->redirect($back);
    }
}
