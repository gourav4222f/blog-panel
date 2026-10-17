<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Database;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ImageService;
use BlogFlow\Services\MediaService;

final class MediaController extends AdminController
{
    private function media(): MediaService
    {
        return $this->app->service(MediaService::class);
    }

    private function findOr404(int $id): array
    {
        return $this->media()->find($id) ?? throw new HttpException(404, 'Media not found.');
    }

    /** Authors may only change media they uploaded. */
    private function canModify(array $row): bool
    {
        return $this->app->auth->can('media.manage') || (int) $row['uploaded_by'] === $this->app->auth->id();
    }

    private function filterSql(string $filter, string $q, array &$params): string
    {
        $where = ['1 = 1'];
        if ($q !== '') {
            $like = '%' . Posts::likeEscape($q) . '%';
            $where[] = '(m.title LIKE ? OR m.filename LIKE ? OR m.alt_text LIKE ? OR m.original_name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        switch ($filter) {
            case 'missing-alt':
                $where[] = "(m.alt_text IS NULL OR m.alt_text = '')";
                break;
            case 'unoptimized':
                $where[] = "(m.optimization_version <> ? AND m.mime <> 'image/gif')";
                $params[] = $this->media()->settingsVersion();
                break;
            case 'large':
                $where[] = 'm.filesize > ?';
                $params[] = max(50, $this->app->settings->int('images.oversized_kb')) * 1024;
                break;
            case 'unused':
                $ids = $this->media()->unusedIds();
                $where[] = $ids ? 'm.id IN (' . Database::placeholders($ids) . ')' : '0 = 1';
                array_push($params, ...$ids);
                break;
        }
        return implode(' AND ', $where);
    }

    public function index(): Response
    {
        $q = trim((string) ($this->request->query['q'] ?? ''));
        $filter = in_array($this->request->query['filter'] ?? '', ['missing-alt', 'unoptimized', 'large', 'unused'], true) ? $this->request->query['filter'] : 'all';
        $view = ($this->request->query['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
        $params = [];
        $where = $this->filterSql($filter, $q, $params);
        $page = $this->pageNumber();
        $per = 40;
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {{media}} m WHERE $where", $params);
        $rows = $this->db->all(
            "SELECT m.*, u.name AS uploader, (SELECT COUNT(*) FROM {{post_media}} pm WHERE pm.media_id = m.id) AS usage_count
             FROM {{media}} m LEFT JOIN {{users}} u ON u.id = m.uploaded_by WHERE $where ORDER BY m.id DESC LIMIT ? OFFSET ?",
            [...$params, $per, ($page - 1) * $per]
        );
        return $this->page('media/index', [
            'pageTitle' => 'Media library',
            'items' => array_map([MediaService::class, 'hydrate'], $rows),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
            'q' => $q,
            'filter' => $filter,
            'mode' => $view,
            'maxUpload' => $this->media()->maxUploadBytes(),
            'caps' => ImageService::capabilities(),
            'stats' => $this->db->one('SELECT COUNT(*) AS n, COALESCE(SUM(filesize),0) AS bytes, COALESCE(SUM(original_filesize),0) AS original FROM {{media}}'),
        ]);
    }

    public function apiList(): Response
    {
        $q = trim((string) ($this->request->query['q'] ?? ''));
        $params = [];
        $where = $this->filterSql('all', $q, $params);
        $page = $this->pageNumber();
        $per = 30;
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {{media}} m WHERE $where", $params);
        $rows = $this->db->all("SELECT m.* FROM {{media}} m WHERE $where ORDER BY m.id DESC LIMIT ? OFFSET ?", [...$params, $per, ($page - 1) * $per]);
        return $this->json([
            'ok' => true,
            'items' => array_map(fn ($r) => $this->media()->toArray(MediaService::hydrate($r)), $rows),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $per)),
        ]);
    }

    public function apiShow(string $id): Response
    {
        return $this->json(['ok' => true, 'item' => $this->media()->toArray($this->findOr404($this->id($id)))]);
    }

    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->json(['ok' => false, 'error' => 'No file received. It may exceed the server’s upload limit (' . bf_bytes($this->media()->maxUploadBytes()) . ').'], 422);
        }
        try {
            $row = $this->media()->upload($file, $this->app->auth->id(), [
                'alt_text' => (string) ($this->request->post['alt_text'] ?? ''),
                'title' => (string) ($this->request->post['title'] ?? ''),
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $this->log('media_upload', 'media', (int) $row['id'], 'Uploaded ' . $row['filename']);
        $item = $this->media()->toArray($row);
        $item['saved_percent'] = $row['original_filesize'] > 0 ? max(0, (int) round((1 - $row['filesize'] / $row['original_filesize']) * 100)) : 0;
        return $this->json(['ok' => true, 'item' => $item]);
    }

    public function edit(string $id): Response
    {
        $row = $this->findOr404($this->id($id));
        $variants = [];
        foreach ($row['variants']['sizes'] ?? [] as $w => $entry) {
            foreach (['jpg', 'png', 'webp', 'avif'] as $f) {
                if (!empty($entry[$f])) {
                    $variants[] = ['label' => $w . 'w ' . strtoupper($f), 'w' => $entry['w'], 'h' => $entry['h'], 'bytes' => $entry['bytes'][$f] ?? 0, 'url' => $this->app->url->upload($entry[$f])];
                }
            }
        }
        foreach (['webp', 'avif'] as $f) {
            if (!empty($row['variants']['full'][$f])) {
                $variants[] = ['label' => 'Full ' . strtoupper($f), 'w' => $row['width'], 'h' => $row['height'], 'bytes' => $row['variants']['full']['bytes'][$f] ?? 0, 'url' => $this->app->url->upload($row['variants']['full'][$f])];
            }
        }
        return $this->page('media/edit', [
            'pageTitle' => 'Edit media',
            'row' => $row,
            'variants' => $variants,
            'usage' => $this->media()->usage((int) $row['id']),
            'url' => $this->media()->url($row),
            'canModify' => $this->canModify($row),
            'totalBytes' => MediaService::totalBytes($row),
            'currentVersion' => $this->media()->settingsVersion(),
            'uploader' => $row['uploaded_by'] ? $this->db->value('SELECT name FROM {{users}} WHERE id = ?', [$row['uploaded_by']]) : null,
        ]);
    }

    public function update(string $id): Response
    {
        $row = $this->findOr404($this->id($id));
        $this->authorize($this->canModify($row), 'You can only edit media you uploaded.');
        $in = $this->request->post;
        $errors = [];
        foreach (['title' => 255, 'alt_text' => 500, 'credit' => 255, 'caption' => 2000] as $k => $max) {
            if (mb_strlen(trim((string) ($in[$k] ?? ''))) > $max) {
                $errors[$k] = sprintf('Must be at most %d characters.', $max);
            }
        }
        if ($errors) {
            return $this->withErrors($this->app->url->admin('media/' . $row['id']), $errors);
        }
        $this->db->update('media', [
            'title' => trim((string) ($in['title'] ?? '')) ?: null,
            'alt_text' => trim((string) ($in['alt_text'] ?? '')) ?: null,
            'caption' => trim((string) ($in['caption'] ?? '')) ?: null,
            'credit' => trim((string) ($in['credit'] ?? '')) ?: null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = ?', [$row['id']]);
        $newName = trim((string) ($in['filename'] ?? ''));
        if ($newName !== '' && $newName !== pathinfo($row['filename'], PATHINFO_FILENAME)) {
            try {
                $this->media()->rename((int) $row['id'], $newName);
            } catch (\RuntimeException $e) {
                $this->flash('error', $e->getMessage());
            }
        }
        \BlogFlow\Services\PageCache::clear();
        $this->log('media_update', 'media', (int) $row['id'], 'Updated details of ' . $row['filename']);
        $this->flash('success', 'Media details saved.');
        return $this->redirect($this->app->url->admin('media/' . $row['id']));
    }

    public function replace(string $id): Response
    {
        $row = $this->findOr404($this->id($id));
        $this->authorize($this->canModify($row), 'You can only replace media you uploaded.');
        $file = $this->request->file('file');
        try {
            if (!$file) {
                throw new \RuntimeException('Choose an image to upload.');
            }
            $new = $this->media()->replace((int) $row['id'], $file);
            $this->log('media_replace', 'media', (int) $row['id'], 'Replaced ' . $row['filename'] . ' with ' . $new['filename']);
            $this->flash('success', 'Image replaced. Every post using it now shows the new image.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect($this->app->url->admin('media/' . $row['id']));
    }

    public function optimizeOne(string $id): Response
    {
        $row = $this->findOr404($this->id($id));
        $this->authorize($this->canModify($row));
        try {
            $new = $this->media()->optimize((int) $row['id']);
            $this->flash('success', sprintf('Optimised: %s → %s.', bf_bytes((int) $new['original_filesize']), bf_bytes((int) $new['filesize'])));
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect($this->app->url->admin('media/' . $row['id']));
    }

    public function delete(string $id): Response
    {
        $row = $this->findOr404($this->id($id));
        $this->authorize($this->canModify($row), 'You can only delete media you uploaded.');
        $usage = $this->media()->usage((int) $row['id']);
        if ($usage && !$this->request->bool('force')) {
            $this->flash('error', sprintf('This image is used in %d place(s). Confirm deletion on the media page to remove it anyway.', count($usage)));
            return $this->redirect($this->app->url->admin('media/' . $row['id']));
        }
        $this->media()->delete((int) $row['id']);
        $this->log('media_delete', 'media', (int) $row['id'], 'Deleted ' . $row['filename']);
        $this->flash('success', 'Image deleted.');
        return $this->redirect($this->app->url->admin('media'));
    }

    // ----- Bulk optimisation -----

    public function optimizePage(): Response
    {
        $version = $this->media()->settingsVersion();
        return $this->page('media/optimize', [
            'pageTitle' => 'Bulk image optimisation',
            'total' => (int) $this->db->value("SELECT COUNT(*) FROM {{media}} WHERE mime <> 'image/gif'"),
            'pending' => (int) $this->db->value("SELECT COUNT(*) FROM {{media}} WHERE mime <> 'image/gif' AND optimization_version <> ?", [$version]),
            'withOriginal' => (int) $this->db->value('SELECT COUNT(*) FROM {{media}} WHERE original_path IS NOT NULL'),
            'stats' => $this->db->one('SELECT COALESCE(SUM(original_filesize),0) AS original, COALESCE(SUM(filesize),0) AS current FROM {{media}}'),
            'caps' => ImageService::capabilities(),
            'settings' => $this->app->settings->group('images'),
        ]);
    }

    /** Processes a small batch per request so shared hosting time limits are never hit; resumable. */
    public function optimizeBatch(): Response
    {
        @set_time_limit(60);
        $version = $this->media()->settingsVersion();
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {{media}} WHERE mime <> 'image/gif'");
        $ids = $this->db->column("SELECT id FROM {{media}} WHERE mime <> 'image/gif' AND optimization_version <> ? ORDER BY id LIMIT 3", [$version]);
        $log = [];
        $start = microtime(true);
        foreach ($ids as $id) {
            try {
                $row = $this->media()->optimize((int) $id);
                $log[] = sprintf('✓ %s — %s (%d variants)', $row['filename'], bf_bytes((int) $row['filesize']), count($row['variants']['sizes'] ?? []));
            } catch (\Throwable $e) {
                // Mark as processed so one broken file cannot block the queue.
                $this->db->update('media', ['optimization_version' => $version], 'id = ?', [(int) $id]);
                $log[] = '✕ #' . $id . ': ' . $e->getMessage();
            }
            if (microtime(true) - $start > 20) {
                break;
            }
        }
        $remaining = (int) $this->db->value("SELECT COUNT(*) FROM {{media}} WHERE mime <> 'image/gif' AND optimization_version <> ?", [$version]);
        if ($remaining === 0 && $ids) {
            $this->log('media_bulk_optimize', 'media', null, 'Bulk image optimisation completed');
        }
        return $this->json([
            'ok' => true,
            'total' => $total,
            'done' => $total - $remaining,
            'remaining' => $remaining,
            'log' => $log,
            'message' => $remaining === 0 ? 'All images are optimised with the current settings.' : null,
        ]);
    }

    // ----- Cleanup -----

    public function cleanup(): Response
    {
        $ids = $this->media()->unusedIds();
        $rows = $ids ? array_map([MediaService::class, 'hydrate'], $this->db->all('SELECT * FROM {{media}} WHERE id IN (' . Database::placeholders(array_slice($ids, 0, 200)) . ') ORDER BY id DESC', array_slice($ids, 0, 200))) : [];
        return $this->page('media/cleanup', [
            'pageTitle' => 'Media cleanup',
            'unused' => $rows,
            'unusedCount' => count($ids),
            'orphans' => $this->media()->orphanFiles(),
        ]);
    }

    public function cleanupRun(): Response
    {
        $deleted = 0;
        $unused = array_flip($this->media()->unusedIds());
        foreach (array_map('intval', (array) ($this->request->post['media_ids'] ?? [])) as $id) {
            if (isset($unused[$id])) {
                $this->media()->delete($id);
                $deleted++;
            }
        }
        $files = 0;
        $orphans = array_flip($this->media()->orphanFiles(5000));
        foreach ((array) ($this->request->post['files'] ?? []) as $rel) {
            $rel = (string) $rel;
            if (isset($orphans[$rel]) && !str_contains($rel, '..') && @unlink(MediaService::uploadsDir() . '/' . $rel)) {
                $files++;
            }
        }
        $this->log('media_cleanup', 'media', null, sprintf('Cleanup removed %d unused media item(s) and %d orphan file(s)', $deleted, $files));
        $this->flash('success', sprintf('Removed %d unused media item(s) and %d orphan file(s).', $deleted, $files));
        return $this->redirect($this->app->url->admin('media/cleanup'));
    }
}
