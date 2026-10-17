<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Validation\Validator;

/**
 * Media library: validated uploads, optimisation, responsive variants, usage tracking
 * and <picture> rendering.
 */
final class MediaService
{
    public const SIZE_KEYS = ['thumb', 'card', 'medium', 'large'];

    private Database $db;

    public function __construct(private App $app)
    {
        $this->db = $app->db;
    }

    public static function uploadsDir(): string
    {
        return BF_ROOT . '/uploads';
    }

    public static function originalsDir(): string
    {
        return BF_ROOT . '/storage/originals';
    }

    /** @return array<string, int> size key => width */
    public function sizes(): array
    {
        $s = $this->app->settings;
        $out = [];
        foreach (self::SIZE_KEYS as $k) {
            $out[$k] = max(80, $s->int('images.size_' . $k));
        }
        asort($out);
        return $out;
    }

    private function quality(): array
    {
        $s = $this->app->settings;
        return [
            'jpg' => max(40, min(100, $s->int('images.jpeg_quality'))),
            'webp' => max(40, min(100, $s->int('images.webp_quality'))),
            'avif' => max(30, min(100, $s->int('images.avif_quality'))),
            'png' => max(0, min(9, $s->int('images.png_compression'))),
        ];
    }

    /** Fingerprint of the optimisation settings; media built with other settings can be re-optimised. */
    public function settingsVersion(): int
    {
        $s = $this->app->settings;
        $key = json_encode([$this->sizes(), $this->quality(), $s->int('images.max_width'), $s->bool('images.generate_webp'),
            $s->bool('images.generate_avif') && ImageService::canWrite('avif')]);
        return (int) sprintf('%u', crc32((string) $key)) % 2_000_000_000;
    }

    public function maxUploadBytes(): int
    {
        $setting = max(1, $this->app->settings->int('images.max_upload_mb')) * 1024 * 1024;
        $server = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        return $server > 0 ? min($setting, $server) : $setting;
    }

    private static function iniBytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /**
     * Handles a $_FILES entry.
     *
     * @param bool $trusted true when the file did not come from an HTTP upload (imports, tests)
     * @throws \RuntimeException user-facing validation errors
     */
    public function upload(array $file, ?int $userId, array $meta = [], bool $trusted = false): array
    {
        $tmp = $this->validateUpload($file, $trusted);
        $info = ImageService::inspect($tmp, $this->app->settings->int('images.max_pixels') * 1_000_000);
        $original = (string) ($file['name'] ?? 'image');
        [$dirRel, $base] = $this->allocateName($original);

        $result = $this->process($tmp, $info, $dirRel, $base);
        $originalPath = $this->keepOriginal($tmp, $info['ext'], $dirRel, $base);

        $now = gmdate('Y-m-d H:i:s');
        $title = trim((string) ($meta['title'] ?? '')) ?: self::titleFromFilename($original);
        $id = $this->db->insert('media', [
            'filename' => basename($result['path']),
            'path' => $result['path'],
            'original_name' => mb_substr($original, 0, 255),
            'mime' => $result['mime'],
            'title' => mb_substr($title, 0, 255),
            'alt_text' => mb_substr(trim((string) ($meta['alt_text'] ?? '')), 0, 500) ?: null,
            'caption' => trim((string) ($meta['caption'] ?? '')) ?: null,
            'credit' => mb_substr(trim((string) ($meta['credit'] ?? '')), 0, 255) ?: null,
            'width' => $result['width'],
            'height' => $result['height'],
            'filesize' => $result['filesize'],
            'original_filesize' => (int) filesize($tmp),
            'original_width' => $info['width'],
            'original_height' => $info['height'],
            'original_path' => $originalPath,
            'variants' => json_encode($result['variants'], JSON_UNESCAPED_SLASHES),
            'checksum' => sha1_file($tmp) ?: null,
            'optimized_at' => $result['optimized'] ? $now : null,
            'optimization_version' => $result['optimized'] ? $this->settingsVersion() : 0,
            'uploaded_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$trusted) {
            @unlink($tmp);
        }
        PageCache::clear();
        return $this->find($id);
    }

    private function validateUpload(array $file, bool $trusted): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the server upload limit of ' . bf_bytes($this->maxUploadBytes()) . '.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the upload (temporary folder problem).',
                default => 'The upload failed.',
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!$trusted && !is_uploaded_file($tmp)) || !is_file($tmp)) {
            throw new \RuntimeException('Invalid upload.');
        }
        $size = (int) filesize($tmp);
        if ($size > $this->maxUploadBytes()) {
            throw new \RuntimeException('The file is larger than the maximum upload size of ' . bf_bytes($this->maxUploadBytes()) . '.');
        }
        $name = (string) ($file['name'] ?? '');
        if (preg_match('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|exe|js|html?|svg)(\.|$)/i', $name)) {
            throw new \RuntimeException('This file type is not allowed.');
        }
        return $tmp;
    }

    public static function titleFromFilename(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[_\-\s]+/', ' ', $base));
        return $base === '' ? 'Image' : mb_convert_case(mb_substr($base, 0, 120), MB_CASE_TITLE);
    }

    /**
     * Chooses a collision-free directory and base filename (without extension).
     *
     * @return array{0: string, 1: string} [relative dir "2026/10", base name]
     */
    private function allocateName(string $original): array
    {
        $dirRel = gmdate('Y') . '/' . gmdate('m');
        $dir = self::uploadsDir() . '/' . $dirRel;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('The uploads folder is not writable.');
        }
        $slug = Validator::slugify(pathinfo($original, PATHINFO_FILENAME), 80) ?: 'image';
        // Avoid names that look like generated size variants.
        $slug = (string) preg_replace('/-\d+w$/', '', $slug);
        $base = $slug;
        for ($i = 2; $i < 10000; $i++) {
            $taken = glob($dir . '/' . $base . '.*') ?: [];
            $taken = $taken || glob($dir . '/' . $base . '-*w.*');
            if (!$taken && !$this->db->value('SELECT 1 FROM {{media}} WHERE path LIKE ?', [$dirRel . '/' . $base . '.%'])) {
                return [$dirRel, $base];
            }
            $base = $slug . '-' . $i;
        }
        return [$dirRel, $slug . '-' . bin2hex(random_bytes(4))];
    }

    private function keepOriginal(string $tmp, string $ext, string $dirRel, string $base): ?string
    {
        if (!$this->app->settings->bool('images.keep_originals')) {
            return null;
        }
        $dir = self::originalsDir() . '/' . $dirRel;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $rel = $dirRel . '/' . $base . '.' . $ext;
        return @copy($tmp, self::originalsDir() . '/' . $rel) ? $rel : null;
    }

    /**
     * Builds the stored image and all variants from a source file.
     *
     * @return array{path: string, mime: string, width: int, height: int, filesize: int, variants: array, optimized: bool}
     */
    private function process(string $source, array $info, string $dirRel, string $base, bool $reencodeFull = true): array
    {
        $dir = self::uploadsDir() . '/' . $dirRel;
        $ext = $info['ext'];

        // Animated GIFs are stored untouched so the animation survives.
        if ($ext === 'gif' && ImageService::isAnimatedGif($source)) {
            $rel = $dirRel . '/' . $base . '.gif';
            if (!@copy($source, self::uploadsDir() . '/' . $rel)) {
                throw new \RuntimeException('Could not store the image.');
            }
            return ['path' => $rel, 'mime' => 'image/gif', 'width' => $info['width'], 'height' => $info['height'],
                'filesize' => (int) filesize(self::uploadsDir() . '/' . $rel), 'variants' => ['sizes' => [], 'full' => []], 'optimized' => false];
        }

        $img = ImageService::load($source, $ext);
        // Formats with limited fallback support are stored as JPEG/PNG; modern formats are added as <source>s.
        $baseExt = match ($ext) {
            'jpg', 'png', 'webp' => $ext,
            default => $img->alpha ? 'png' : 'jpg',
        };
        if (!ImageService::canWrite($baseExt)) {
            $baseExt = ImageService::canWrite('jpg') ? 'jpg' : 'png';
        }
        $q = $this->quality();
        $maxWidth = max(400, $this->app->settings->int('images.max_width'));
        $full = ImageService::resize($img, $maxWidth);

        $formats = [];
        if ($this->app->settings->bool('images.generate_webp') && $baseExt !== 'webp' && ImageService::canWrite('webp')) {
            $formats[] = 'webp';
        }
        if ($this->app->settings->bool('images.generate_avif') && ImageService::canWrite('avif')) {
            $formats[] = 'avif';
        }

        $relFull = $dirRel . '/' . $base . '.' . $baseExt;
        $absFull = self::uploadsDir() . '/' . $relFull;
        if ($reencodeFull || !is_file($absFull)) {
            $tmpOut = $absFull . '.tmp-' . bin2hex(random_bytes(3));
            if (!ImageService::save($full, $tmpOut, $baseExt, $q)) {
                @unlink($tmpOut);
                throw new \RuntimeException('Could not encode the image.');
            }
            // Keep the source bytes when re-encoding would make an unresized file larger.
            if ($ext === $baseExt && $full->width === $info['width'] && filesize($tmpOut) >= filesize($source)) {
                @unlink($tmpOut);
                if (!@copy($source, $absFull)) {
                    throw new \RuntimeException('Could not store the image.');
                }
                $this->stripIfNeeded($absFull, $baseExt, $full, $q);
            } else {
                @rename($tmpOut, $absFull);
            }
        }

        $variants = ['sizes' => [], 'full' => ['w' => $full->width, 'h' => $full->height, 'bytes' => [$baseExt => (int) filesize($absFull)]]];
        foreach ($formats as $f) {
            $rel = $dirRel . '/' . $base . '.' . $f;
            if (ImageService::save($full, self::uploadsDir() . '/' . $rel, $f, $q)) {
                $variants['full'][$f] = $rel;
                $variants['full']['bytes'][$f] = (int) filesize(self::uploadsDir() . '/' . $rel);
            }
        }

        foreach ($this->sizes() as $key => $width) {
            if ($width >= $full->width) {
                continue; // never upscale; the full image covers this size
            }
            $resized = ImageService::resize($full, $width);
            $entry = ['key' => $key, 'w' => $resized->width, 'h' => $resized->height, 'bytes' => []];
            foreach (array_merge([$baseExt], $formats) as $f) {
                $rel = $dirRel . '/' . $base . '-' . $width . 'w.' . $f;
                if (ImageService::save($resized, self::uploadsDir() . '/' . $rel, $f, $q)) {
                    $entry[$f] = $rel;
                    $entry['bytes'][$f] = (int) filesize(self::uploadsDir() . '/' . $rel);
                }
            }
            if (isset($entry[$baseExt])) {
                $variants['sizes'][(string) $width] = $entry;
            }
        }
        $variants['base'] = $baseExt;

        return [
            'path' => $relFull,
            'mime' => array_search($baseExt, ImageService::MIME_EXT, true) ?: 'image/jpeg',
            'width' => $full->width,
            'height' => $full->height,
            'filesize' => (int) filesize($absFull),
            'variants' => $variants,
            'optimized' => true,
        ];
    }

    /** When the source bytes are kept, still remove EXIF/GPS metadata from JPEGs if configured. */
    private function stripIfNeeded(string $abs, string $ext, ImageHandle $img, array $q): void
    {
        if ($ext !== 'jpg' || !$this->app->settings->bool('images.strip_metadata')) {
            return;
        }
        $raw = (string) @file_get_contents($abs, false, null, 0, 65536);
        if (str_contains($raw, "Exif\0\0") || str_contains($raw, 'http://ns.adobe.com/xap/')) {
            ImageService::save($img, $abs, 'jpg', $q);
        }
    }

    public function find(int $id): ?array
    {
        $row = $this->db->one('SELECT * FROM {{media}} WHERE id = ?', [$id]);
        return $row ? self::hydrate($row) : null;
    }

    /** @return array<int, array> keyed by id */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ($this->db->all('SELECT * FROM {{media}} WHERE id IN (' . Database::placeholders($ids) . ')', $ids) as $row) {
            $out[(int) $row['id']] = self::hydrate($row);
        }
        return $out;
    }

    public static function hydrate(array $row): array
    {
        $row['variants'] = is_string($row['variants'] ?? null) ? (json_decode($row['variants'], true) ?: []) : ($row['variants'] ?? []);
        return $row;
    }

    /** All files (relative to uploads/) belonging to a media row. */
    public static function files(array $row): array
    {
        $files = [$row['path']];
        $v = $row['variants'] ?? [];
        foreach (['webp', 'avif'] as $f) {
            if (!empty($v['full'][$f])) {
                $files[] = $v['full'][$f];
            }
        }
        foreach ($v['sizes'] ?? [] as $entry) {
            foreach (['jpg', 'png', 'webp', 'avif'] as $f) {
                if (!empty($entry[$f])) {
                    $files[] = $entry[$f];
                }
            }
        }
        return array_values(array_unique($files));
    }

    public static function totalBytes(array $row): int
    {
        $sum = 0;
        foreach (self::files($row) as $f) {
            $abs = self::uploadsDir() . '/' . $f;
            $sum += is_file($abs) ? (int) filesize($abs) : 0;
        }
        return $sum;
    }

    /**
     * Re-optimises an existing image with the current settings.
     * Uses the private original when available; otherwise only builds missing variants
     * from the stored file (avoiding repeated lossy re-encoding).
     */
    public function optimize(int $id): array
    {
        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException('Media not found.');
        }
        if ($row['mime'] === 'image/gif') {
            $this->db->update('media', ['optimization_version' => $this->settingsVersion()], 'id = ?', [$id]);
            return $this->find($id);
        }
        $dirRel = dirname($row['path']);
        $base = pathinfo($row['path'], PATHINFO_FILENAME);
        $origAbs = $row['original_path'] ? self::originalsDir() . '/' . $row['original_path'] : null;
        $useOriginal = $origAbs && is_file($origAbs);
        $source = $useOriginal ? $origAbs : self::uploadsDir() . '/' . $row['path'];
        if (!is_file($source)) {
            throw new \RuntimeException('The image file is missing on disk.');
        }
        $info = ImageService::inspect($source, $this->app->settings->int('images.max_pixels') * 1_000_000);
        $oldFiles = self::files($row);

        // Work on a copy of the source so a failure never leaves the library without its file.
        $work = BF_ROOT . '/storage/tmp/opt-' . $id . '-' . bin2hex(random_bytes(3)) . '.' . $info['ext'];
        if (!@copy($source, $work)) {
            throw new \RuntimeException('Could not prepare the image for optimisation.');
        }
        try {
            $result = $this->process($work, $info, $dirRel, $base, $useOriginal);
        } finally {
            @unlink($work);
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->db->update('media', [
            'path' => $result['path'],
            'filename' => basename($result['path']),
            'mime' => $result['mime'],
            'width' => $result['width'],
            'height' => $result['height'],
            'filesize' => $result['filesize'],
            'variants' => json_encode($result['variants'], JSON_UNESCAPED_SLASHES),
            'optimized_at' => $now,
            'optimization_version' => $this->settingsVersion(),
            'updated_at' => $now,
        ], 'id = ?', [$id]);
        $new = $this->find($id);
        foreach (array_diff($oldFiles, self::files($new)) as $stale) {
            @unlink(self::uploadsDir() . '/' . $stale);
        }
        PageCache::clear();
        return $new;
    }

    /** Replaces the image file while keeping the media ID, metadata and all references. */
    public function replace(int $id, array $file, bool $trusted = false): array
    {
        $row = $this->find($id);
        if (!$row) {
            throw new \RuntimeException('Media not found.');
        }
        $tmp = $this->validateUpload($file, $trusted);
        $info = ImageService::inspect($tmp, $this->app->settings->int('images.max_pixels') * 1_000_000);
        [$dirRel, $base] = $this->allocateName((string) ($file['name'] ?? $row['filename']));
        $result = $this->process($tmp, $info, $dirRel, $base);
        $originalPath = $this->keepOriginal($tmp, $info['ext'], $dirRel, $base);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->update('media', [
            'filename' => basename($result['path']),
            'path' => $result['path'],
            'original_name' => mb_substr((string) ($file['name'] ?? ''), 0, 255),
            'mime' => $result['mime'],
            'width' => $result['width'],
            'height' => $result['height'],
            'filesize' => $result['filesize'],
            'original_filesize' => (int) filesize($tmp),
            'original_width' => $info['width'],
            'original_height' => $info['height'],
            'original_path' => $originalPath,
            'variants' => json_encode($result['variants'], JSON_UNESCAPED_SLASHES),
            'checksum' => sha1_file($tmp) ?: null,
            'optimized_at' => $result['optimized'] ? $now : null,
            'optimization_version' => $result['optimized'] ? $this->settingsVersion() : 0,
            'updated_at' => $now,
        ], 'id = ?', [$id]);
        $this->removeFiles($row);
        if (!$trusted) {
            @unlink($tmp);
        }
        PageCache::clear();
        return $this->find($id);
    }

    /** Renames an image's files (all variants) to a new descriptive filename. */
    public function rename(int $id, string $newName): array
    {
        $row = $this->find($id) ?? throw new \RuntimeException('Media not found.');
        $slug = Validator::slugify($newName, 80);
        if ($slug === '') {
            throw new \RuntimeException('Enter a filename using letters or numbers.');
        }
        if ($slug === pathinfo($row['path'], PATHINFO_FILENAME)) {
            return $row;
        }
        [$dirRel, $base] = $this->allocateName($slug . '.' . pathinfo($row['path'], PATHINFO_EXTENSION));
        $oldBase = pathinfo($row['path'], PATHINFO_FILENAME);
        $map = [];
        foreach (self::files($row) as $f) {
            $rest = substr(basename($f), strlen($oldBase));
            $map[$f] = $dirRel . '/' . $base . $rest;
        }
        foreach ($map as $from => $to) {
            if (!@copy(self::uploadsDir() . '/' . $from, self::uploadsDir() . '/' . $to)) {
                foreach ($map as $t) {
                    @unlink(self::uploadsDir() . '/' . $t);
                }
                throw new \RuntimeException('Could not rename the files.');
            }
        }
        $variants = $row['variants'];
        array_walk_recursive($variants, function (&$value) use ($map) {
            if (is_string($value) && isset($map[$value])) {
                $value = $map[$value];
            }
        });
        $json = json_encode($variants, JSON_UNESCAPED_SLASHES);
        $this->db->update('media', [
            'path' => $map[$row['path']],
            'filename' => basename($map[$row['path']]),
            'variants' => $json,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        foreach (array_keys($map) as $from) {
            @unlink(self::uploadsDir() . '/' . $from);
        }
        PageCache::clear();
        return $this->find($id);
    }

    /** Files in uploads/ that no media row references. @return string[] relative paths */
    public function orphanFiles(int $limit = 500): array
    {
        $known = [];
        foreach ($this->db->all('SELECT path, variants FROM {{media}}') as $r) {
            foreach (self::files(self::hydrate($r)) as $f) {
                $known[$f] = true;
            }
        }
        $out = [];
        $dir = self::uploadsDir();
        if (!is_dir($dir)) {
            return [];
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
            if ($file->isFile() && !str_starts_with(basename($rel), '.') && !isset($known[$rel]) && preg_match('/\.(jpe?g|png|webp|avif|gif|tmp-[a-f0-9]+)$/i', $rel)) {
                $out[] = $rel;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        sort($out);
        return $out;
    }

    private function removeFiles(array $row): void
    {
        foreach (self::files($row) as $f) {
            @unlink(self::uploadsDir() . '/' . $f);
        }
        if (!empty($row['original_path'])) {
            @unlink(self::originalsDir() . '/' . $row['original_path']);
        }
    }

    public function delete(int $id): void
    {
        $row = $this->find($id);
        if (!$row) {
            return;
        }
        $this->db->transaction(function (Database $db) use ($id) {
            $db->delete('media', 'id = ?', [$id]);
        });
        $this->removeFiles($row);
        PageCache::clear();
    }

    /**
     * Where a media item is referenced.
     *
     * @return array<int, array{type: string, label: string, url: ?string}>
     */
    public function usage(int $id): array
    {
        $out = [];
        $posts = $this->db->all(
            'SELECT DISTINCT p.id, p.title FROM {{posts}} p
             LEFT JOIN {{post_media}} pm ON pm.post_id = p.id AND pm.media_id = ?
             WHERE pm.media_id IS NOT NULL OR p.featured_image_id = ? OR p.og_image_id = ?',
            [$id, $id, $id]
        );
        foreach ($posts as $p) {
            $out[] = ['type' => 'Post', 'label' => $p['title'], 'url' => $this->app->url->admin('posts/' . $p['id'] . '/edit')];
        }
        foreach ($this->db->all('SELECT id, name FROM {{categories}} WHERE image_id = ?', [$id]) as $c) {
            $out[] = ['type' => 'Category', 'label' => $c['name'], 'url' => $this->app->url->admin('categories/' . $c['id'] . '/edit')];
        }
        foreach ($this->db->all('SELECT id, display_name FROM {{authors}} WHERE photo_id = ?', [$id]) as $a) {
            $out[] = ['type' => 'Author', 'label' => $a['display_name'], 'url' => $this->app->url->admin('authors/' . $a['id'] . '/edit')];
        }
        foreach (SettingsSchema::groups() as $g => $def) {
            foreach ($def['fields'] as $k => $f) {
                if ($f['type'] === 'media' && (int) $this->app->settings->get($g . '.' . $k) === $id) {
                    $out[] = ['type' => 'Setting', 'label' => $def['title'] . ' → ' . $f['label'], 'url' => null];
                }
            }
        }
        return $out;
    }

    /** IDs of media not referenced anywhere. */
    public function unusedIds(): array
    {
        $settingIds = [];
        foreach (SettingsSchema::groups() as $g => $def) {
            foreach ($def['fields'] as $k => $f) {
                if ($f['type'] === 'media' && ($v = (int) $this->app->settings->get($g . '.' . $k)) > 0) {
                    $settingIds[] = $v;
                }
            }
        }
        $ids = $this->db->column(
            'SELECT m.id FROM {{media}} m
             WHERE NOT EXISTS (SELECT 1 FROM {{post_media}} pm WHERE pm.media_id = m.id)
               AND NOT EXISTS (SELECT 1 FROM {{posts}} p WHERE p.featured_image_id = m.id OR p.og_image_id = m.id)
               AND NOT EXISTS (SELECT 1 FROM {{categories}} c WHERE c.image_id = m.id)
               AND NOT EXISTS (SELECT 1 FROM {{authors}} a WHERE a.photo_id = m.id)
             ORDER BY m.id DESC'
        );
        return array_values(array_diff(array_map('intval', $ids), $settingIds));
    }

    // ----- URLs & rendering -----

    public function url(array $row, ?string $size = null, bool $absolute = true): string
    {
        $rel = $row['path'];
        if ($size !== null) {
            $width = $this->sizes()[$size] ?? null;
            $entry = $width !== null ? ($row['variants']['sizes'][(string) $width] ?? null) : null;
            $base = $row['variants']['base'] ?? pathinfo($row['path'], PATHINFO_EXTENSION);
            if ($entry && !empty($entry[$base])) {
                $rel = $entry[$base];
            }
        }
        return $absolute ? $this->app->url->upload($rel) : $this->app->url->uploadPath($rel);
    }

    /** Best image for social sharing: the largest variant up to ~1440px. */
    public function socialImage(array $row): array
    {
        $best = ['url' => $this->app->url->upload($row['path']), 'w' => (int) $row['width'], 'h' => (int) $row['height']];
        $base = $row['variants']['base'] ?? pathinfo($row['path'], PATHINFO_EXTENSION);
        if ((int) $row['width'] > 1600) {
            foreach ($row['variants']['sizes'] ?? [] as $w => $entry) {
                if ((int) $w >= 1080 && (int) $w <= 1600 && !empty($entry[$base])) {
                    $best = ['url' => $this->app->url->upload($entry[$base]), 'w' => (int) $entry['w'], 'h' => (int) $entry['h']];
                }
            }
        }
        $best['mime'] = $row['mime'];
        return $best;
    }

    /** @return string srcset for a format ('base', 'webp', 'avif') or '' when unavailable. */
    public function srcset(array $row, string $format, bool $absolute = true): string
    {
        $v = $row['variants'] ?? [];
        $base = $v['base'] ?? pathinfo($row['path'], PATHINFO_EXTENSION);
        $f = $format === 'base' ? $base : $format;
        $u = fn (string $rel) => $absolute ? $this->app->url->upload($rel) : $this->app->url->uploadPath($rel);
        $parts = [];
        foreach ($v['sizes'] ?? [] as $entry) {
            if (!empty($entry[$f])) {
                $parts[(int) $entry['w']] = $u($entry[$f]) . ' ' . (int) $entry['w'] . 'w';
            }
        }
        $fullFile = $f === $base ? $row['path'] : ($v['full'][$f] ?? null);
        if ($fullFile) {
            $parts[(int) $row['width']] = $u($fullFile) . ' ' . (int) $row['width'] . 'w';
        }
        if ($format !== 'base' && count($parts) === 0) {
            return '';
        }
        ksort($parts);
        return implode(', ', $parts);
    }

    /**
     * Renders a responsive <picture>.
     *
     * @param array $o alt, sizes, class, loading (lazy|eager), priority (bool), ratio (crop via CSS)
     */
    public function picture(array $row, array $o = []): string
    {
        $alt = (string) ($o['alt'] ?? $row['alt_text'] ?? '');
        $sizes = (string) ($o['sizes'] ?? '100vw');
        $priority = !empty($o['priority']);
        $loading = $priority ? 'eager' : ($o['loading'] ?? 'lazy');
        $absolute = $o['absolute'] ?? true;
        $fallbackSize = $o['fallback'] ?? 'medium';
        $html = '<picture>';
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $fmt => $type) {
            $set = $this->srcset($row, $fmt, $absolute);
            if ($set !== '') {
                $html .= '<source type="' . $type . '" srcset="' . e($set) . '" sizes="' . e($sizes) . '">';
            }
        }
        $attrs = [
            'src' => $this->url($row, $fallbackSize, $absolute),
            'srcset' => $this->srcset($row, 'base', $absolute),
            'sizes' => $sizes,
            'width' => (string) (int) $row['width'],
            'height' => (string) (int) $row['height'],
            'alt' => $alt,
            'loading' => $loading,
            'decoding' => $priority ? 'sync' : 'async',
        ];
        if ($priority) {
            $attrs['fetchpriority'] = 'high';
        }
        if (!empty($o['class'])) {
            $attrs['class'] = $o['class'];
        }
        $html .= '<img';
        foreach ($attrs as $k => $v) {
            if ($v === '' && $k !== 'alt') {
                continue;
            }
            $html .= ' ' . $k . '="' . e($v) . '"';
        }
        return $html . '></picture>';
    }

    /** Data sent to the admin media picker / editor. */
    public function toArray(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'alt' => (string) ($row['alt_text'] ?? ''),
            'caption' => (string) ($row['caption'] ?? ''),
            'credit' => (string) ($row['credit'] ?? ''),
            'filename' => (string) $row['filename'],
            'mime' => (string) $row['mime'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'filesize' => (int) $row['filesize'],
            'original_filesize' => (int) $row['original_filesize'],
            'url' => $this->url($row, null, true),
            'path' => $this->url($row, null, false),
            'thumb' => $this->url($row, 'thumb', false),
            'medium' => $this->url($row, 'medium', false),
            'created_at' => (string) $row['created_at'],
        ];
    }
}
