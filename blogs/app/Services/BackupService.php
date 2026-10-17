<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;

/**
 * Database backups (gzip SQL of this installation's prefixed tables), restore with a strict
 * statement allowlist, media archives and portable configuration export/import.
 */
final class BackupService
{
    public const HEADER = '-- BlogFlow CMS backup';

    public function __construct(private App $app)
    {
    }

    public static function dir(): string
    {
        return BF_ROOT . '/storage/backups';
    }

    public static function validName(string $file): bool
    {
        return (bool) preg_match('/^blogflow-(db|media|safety)-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.(sql\.gz|zip)$/', $file);
    }

    public function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/blogflow-*') ?: [] as $f) {
            if (self::validName(basename($f))) {
                $out[] = ['file' => basename($f), 'size' => filesize($f), 'time' => filemtime($f), 'type' => str_contains(basename($f), '-media-') ? 'Media' : (str_contains(basename($f), '-safety-') ? 'Safety (before restore)' : 'Database')];
            }
        }
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    private function newName(string $kind, string $ext): string
    {
        return sprintf('blogflow-%s-%s-%s.%s', $kind, gmdate('Ymd-His'), bin2hex(random_bytes(3)), $ext);
    }

    private function tables(): array
    {
        $prefix = $this->app->db->prefix();
        $all = $this->app->db->pdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        return array_values(array_filter($all, fn ($t) => $prefix === '' ? true : str_starts_with($t, $prefix)));
    }

    public function createDatabaseBackup(string $kind = 'db'): string
    {
        @set_time_limit(300);
        $db = $this->app->db;
        $pdo = $db->pdo();
        $name = $this->newName($kind, 'sql.gz');
        $path = self::dir() . '/' . $name;
        $gz = gzopen($path, 'wb6');
        if (!$gz) {
            throw new \RuntimeException('Cannot write to storage/backups.');
        }
        gzwrite($gz, self::HEADER . "\n-- version: " . BF_VERSION . "\n-- prefix: " . $db->prefix() . "\n-- created: " . gmdate('c') . "\n-- server: " . $db->serverVersion() . "\n");
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n");
        foreach ($this->tables() as $table) {
            $q = Database::ident($table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $q)->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, 'DROP TABLE IF EXISTS ' . $q . ";\n");
            gzwrite($gz, preg_replace('/\s*\n\s*/', ' ', $create) . ";\n");
            $stmt = $pdo->query('SELECT * FROM ' . $q, \PDO::FETCH_ASSOC);
            $batch = [];
            $cols = null;
            while ($row = $stmt->fetch()) {
                $cols ??= '(' . implode(', ', array_map([Database::class, 'ident'], array_keys($row))) . ')';
                $batch[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : str_replace(["\r", "\n"], ['\\r', '\\n'], $pdo->quote((string) $v)), $row)) . ')';
                if (count($batch) >= 100) {
                    gzwrite($gz, 'INSERT INTO ' . $q . ' ' . $cols . ' VALUES ' . implode(', ', $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, 'INSERT INTO ' . $q . ' ' . $cols . ' VALUES ' . implode(', ', $batch) . ";\n");
            }
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- end of backup\n");
        gzclose($gz);
        @chmod($path, 0640);
        $this->app->settings->set('system.last_backup_at', gmdate('Y-m-d H:i:s'));
        return $name;
    }

    /**
     * Restores a BlogFlow backup file. Only this installation's tables can be touched.
     *
     * @return array{statements: int, safety: string}
     */
    public function restore(string $file): array
    {
        @set_time_limit(600);
        $fh = @gzopen($file, 'rb');
        if (!$fh) {
            throw new \RuntimeException('The file could not be read.');
        }
        $first = (string) gzgets($fh, 4096);
        if (trim($first) !== self::HEADER) {
            gzclose($fh);
            throw new \RuntimeException('This is not a BlogFlow CMS backup file.');
        }
        $oldPrefix = null;
        $lines = [];
        $ended = false;
        while (($line = gzgets($fh, 64 * 1024 * 1024)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '-- prefix: ')) {
                $oldPrefix = trim(substr($line, 11));
                continue;
            }
            if ($line === '-- end of backup') {
                $ended = true;
                continue;
            }
            if (str_starts_with($line, '--')) {
                continue;
            }
            $lines[] = $line;
        }
        gzclose($fh);
        if ($oldPrefix === null || !preg_match('/^[A-Za-z0-9_]*$/', $oldPrefix)) {
            throw new \RuntimeException('The backup header is missing its table prefix.');
        }
        if (!$ended) {
            throw new \RuntimeException('The backup file is incomplete (truncated).');
        }
        $newPrefix = $this->app->db->prefix();
        $p = preg_quote($oldPrefix, '/');
        $statements = [];
        foreach ($lines as $i => $line) {
            $ok = $line === 'SET FOREIGN_KEY_CHECKS=0;' || $line === 'SET FOREIGN_KEY_CHECKS=1;'
                || preg_match('/^DROP TABLE IF EXISTS `' . $p . '[a-z_]+`;$/', $line)
                || preg_match('/^CREATE TABLE `' . $p . '[a-z_]+` \(.*\) ENGINE=InnoDB[^;]*;$/', $line)
                || preg_match('/^INSERT INTO `' . $p . '[a-z_]+` \([`a-z_, ]+\) VALUES \(.*\);$/s', $line);
            if (!$ok) {
                throw new \RuntimeException('Unexpected statement on line ' . ($i + 1) . '. Only BlogFlow table data can be restored.');
            }
            if (str_starts_with($line, 'CREATE TABLE') && preg_match('/REFERENCES `(?!' . $p . ')/', $line)) {
                throw new \RuntimeException('The backup references tables outside this installation.');
            }
            if ($oldPrefix !== $newPrefix) {
                if (str_starts_with($line, 'INSERT INTO')) {
                    $pos = strpos($line, ' VALUES ');
                    $line = str_replace('`' . $oldPrefix, '`' . $newPrefix, substr($line, 0, $pos)) . substr($line, $pos);
                } elseif ($oldPrefix !== '') {
                    $line = str_replace('`' . $oldPrefix, '`' . $newPrefix, $line);
                }
            }
            $statements[] = $line;
        }
        $safety = $this->createDatabaseBackup('safety');
        // Dedicated connection: multi-statement execution is disabled, so each line runs alone.
        $pdo = Database::connect($this->app->config['db']);
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
        } catch (\PDOException $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            throw new \RuntimeException('Restore stopped by a database error. Your previous data is saved in ' . $safety . '. Error: ' . $e->getMessage());
        }
        $this->app->cache->flush();
        PageCache::clear();
        Scheduler::refresh($this->app->db);
        return ['statements' => count($statements), 'safety' => $safety];
    }

    public function createMediaArchive(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is not available. Download the uploads/ folder with FTP instead.');
        }
        @set_time_limit(600);
        $name = $this->newName('media', 'zip');
        $zip = new \ZipArchive();
        if ($zip->open(self::dir() . '/' . $name, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot create the archive in storage/backups.');
        }
        $root = MediaService::uploadsDir();
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() !== '.htaccess') {
                $zip->addFile($f->getPathname(), 'uploads/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/'));
                $count++;
            }
        }
        $zip->close();
        if ($count === 0) {
            @unlink(self::dir() . '/' . $name);
            throw new \RuntimeException('There are no uploaded files to archive yet.');
        }
        return $name;
    }

    // ----- Portable configuration -----

    /** Sections available for export/import. */
    public const CONFIG_SECTIONS = [
        'appearance' => ['Theme, typography, header & footer', ['theme', 'typography', 'layout']],
        'seo' => ['SEO templates, schema & sitemap', ['seo', 'schema', 'sitemap']],
        'social' => ['Social media', ['social']],
        'general' => ['General display options', ['general', 'images']],
        'integrations' => ['Integrations (analytics IDs, newsletter)', ['integrations']],
        'menus' => ['Menus', []],
    ];

    /** Keys never exported: secrets, raw code, site-specific URLs and media IDs. */
    private static function exportable(string $group, string $key, array $field): bool
    {
        if (!empty($field['secret']) || ($field['export'] ?? true) === false || $field['type'] === 'media') {
            return false;
        }
        return !in_array($group . '.' . $key, ['general.main_site_url', 'general.main_site_name', 'general.blog_name', 'url.blog_url'], true);
    }

    public function exportConfig(array $sections): array
    {
        $out = ['generator' => 'BlogFlow CMS', 'type' => 'site-config', 'version' => BF_VERSION, 'exported_at' => gmdate('c'), 'settings' => [], 'menus' => []];
        foreach ($sections as $section) {
            foreach (self::CONFIG_SECTIONS[$section][1] ?? [] as $group) {
                foreach (SettingsSchema::group($group)['fields'] as $key => $field) {
                    if (self::exportable($group, $key, $field)) {
                        $out['settings'][$group][$key] = $this->app->settings->get($group . '.' . $key);
                    }
                }
            }
        }
        if (in_array('menus', $sections, true)) {
            foreach (array_keys(MenuService::LOCATIONS) as $loc) {
                $items = $this->app->service(MenuService::class)->items($loc);
                $byId = array_column($items, null, 'id');
                foreach ($items as $i) {
                    $slug = null;
                    if (in_array($i['link_type'], ['category', 'tag', 'post'], true) && $i['target_id']) {
                        $table = ['category' => 'categories', 'tag' => 'tags', 'post' => 'posts'][$i['link_type']];
                        $slug = $this->app->db->value('SELECT slug FROM {{' . $table . '}} WHERE id = ?', [$i['target_id']]);
                    }
                    $out['menus'][$loc][] = [
                        'key' => 'm' . $i['id'],
                        'parent_key' => $i['parent_id'] && isset($byId[$i['parent_id']]) ? 'm' . $i['parent_id'] : '',
                        'label' => $i['label'],
                        'link_type' => $i['link_type'],
                        'url' => $i['url'],
                        'target_slug' => $slug,
                        'new_tab' => (bool) $i['new_tab'],
                        'is_visible' => (bool) $i['is_visible'],
                    ];
                }
            }
        }
        return $out;
    }

    /** @return array{imported: string[], errors: string[]} */
    public function importConfig(array $data, array $sections): array
    {
        if (($data['type'] ?? '') !== 'site-config' || !is_array($data['settings'] ?? null)) {
            throw new \RuntimeException('This is not a BlogFlow configuration export.');
        }
        $imported = [];
        $errors = [];
        $rows = [];
        foreach ($sections as $section) {
            foreach (self::CONFIG_SECTIONS[$section][1] ?? [] as $group) {
                $incoming = $data['settings'][$group] ?? null;
                if (!is_array($incoming)) {
                    continue;
                }
                $fields = SettingsSchema::group($group)['fields'];
                $current = $this->app->settings->group($group);
                $merged = $current;
                foreach ($incoming as $k => $v) {
                    if (isset($fields[$k]) && self::exportable($group, $k, $fields[$k])) {
                        $merged[$k] = $v;
                    }
                }
                [$clean, $groupErrors] = Settings::validateGroup($group, array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : $v, $merged), $current, false);
                if ($groupErrors) {
                    $errors[] = SettingsSchema::group($group)['title'] . ': ' . implode(' ', $groupErrors);
                    continue;
                }
                foreach ($clean as $k => $v) {
                    if (isset($incoming[$k]) && self::exportable($group, $k, $fields[$k])) {
                        $rows[$group . '.' . $k] = $v;
                    }
                }
                $imported[] = SettingsSchema::group($group)['title'];
            }
        }
        if ($rows) {
            $this->app->settings->setMany($rows);
        }
        if (in_array('menus', $sections, true) && is_array($data['menus'] ?? null)) {
            foreach ($data['menus'] as $loc => $items) {
                if (!isset(MenuService::LOCATIONS[$loc]) || !is_array($items)) {
                    continue;
                }
                $resolved = [];
                foreach ($items as $i) {
                    if (!is_array($i)) {
                        continue;
                    }
                    if (in_array($i['link_type'] ?? '', ['category', 'tag', 'post'], true)) {
                        $table = ['category' => 'categories', 'tag' => 'tags', 'post' => 'posts'][$i['link_type']];
                        $i['target_id'] = $this->app->db->value('SELECT id FROM {{' . $table . '}} WHERE slug = ?', [(string) ($i['target_slug'] ?? '')]);
                        if (!$i['target_id']) {
                            continue;
                        }
                    }
                    $resolved[] = $i;
                }
                $menuErrors = $this->app->service(MenuService::class)->save($loc, $resolved);
                if ($menuErrors) {
                    $errors[] = 'Menu ' . $loc . ': ' . reset($menuErrors);
                } else {
                    $imported[] = MenuService::LOCATIONS[$loc];
                }
            }
        }
        $this->app->cache->flush();
        PageCache::clear();
        return ['imported' => $imported, 'errors' => $errors];
    }
}
