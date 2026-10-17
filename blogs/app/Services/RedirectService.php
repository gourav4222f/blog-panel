<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Core\Request;
use BlogFlow\Core\Response;
use BlogFlow\Validation\Validator;

/**
 * 301/302 redirect manager. Sources are paths relative to the blog root ("/old-post").
 * Destinations are either blog-relative paths or absolute http(s) URLs entered by staff.
 */
final class RedirectService
{
    private Database $db;

    public function __construct(private App $app)
    {
        $this->db = $app->db;
    }

    public static function normalize(string $path): string
    {
        $path = trim($path);
        $q = strpos($path, '?');
        if ($q !== false) {
            $path = substr($path, 0, $q);
        }
        $path = rawurldecode($path);
        $path = '/' . ltrim($path, '/');
        $path = (string) preg_replace('#/{2,}#', '/', $path);
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** Accepts "/blogs/old", "https://site/blogs/old" or "old" and returns "/old". */
    public function normalizeSource(string $input): string
    {
        $input = trim($input);
        if (preg_match('#^https?://#i', $input)) {
            $rel = $this->app->url->relativePath($input);
            $input = $rel ?? (string) parse_url($input, PHP_URL_PATH);
        } elseif ($this->app->url->basePath() !== '' && str_starts_with($input, $this->app->url->basePath() . '/')) {
            $input = substr($input, strlen($this->app->url->basePath()));
        }
        return self::normalize($input);
    }

    public function normalizeDestination(string $input): string
    {
        $input = trim($input);
        if (preg_match('#^https?://#i', $input)) {
            $rel = $this->app->url->relativePath($input);
            if ($rel !== null) {
                $query = (string) parse_url($input, PHP_URL_QUERY);
                return self::normalize($rel) . ($query !== '' ? '?' . $query : '');
            }
            return $input;
        }
        $query = str_contains($input, '?') ? substr($input, strpos($input, '?')) : '';
        return self::normalize($input) . $query;
    }

    private function map(): array
    {
        return $this->app->cache->remember('redirects', 0, function () {
            $map = [];
            foreach ($this->db->all('SELECT id, source_path, destination, status_code FROM {{redirects}} WHERE is_enabled = 1') as $r) {
                $map[$r['source_path']] = [(int) $r['id'], $r['destination'], (int) $r['status_code']];
            }
            return $map;
        });
    }

    public function clearCache(): void
    {
        $this->app->cache->forget('redirects');
    }

    public function match(Request $request): ?Response
    {
        $map = $this->map();
        if (!$map) {
            return null;
        }
        $path = self::normalize($request->path);
        if (!isset($map[$path])) {
            return null;
        }
        [$id, $dest, $code] = $map[$path];
        $target = $this->resolve($dest);
        $query = $request->rawQuery();
        if ($query !== '' && !str_contains($target, '?')) {
            $target .= '?' . $query;
        }
        try {
            $this->db->query('UPDATE {{redirects}} SET hits = hits + 1, last_hit_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);
        } catch (\Throwable) {
            // Statistics must never break a redirect.
        }
        return Response::redirect($target, in_array($code, [301, 302, 307, 308], true) ? $code : 301)
            ->header('Cache-Control', $code === 301 || $code === 308 ? 'public, max-age=3600' : 'no-store');
    }

    /** Absolute URL for a stored destination. */
    public function resolve(string $dest): string
    {
        if (preg_match('#^https?://#i', $dest)) {
            return $dest;
        }
        $path = strtok($dest, '?') ?: '/';
        $query = str_contains($dest, '?') ? substr($dest, strpos($dest, '?')) : '';
        return ($path === '/' ? $this->app->url->home() : $this->app->url->to($path)) . $query;
    }

    /**
     * @return array<string, string> field => error
     */
    public function validate(string $source, string $dest, ?int $exceptId = null): array
    {
        $errors = [];
        if ($source === '/' || $source === '') {
            $errors['source_path'] = 'The blog homepage cannot be redirected.';
        } elseif (preg_match('#^/(admin|install|assets|uploads)(/|$)#', $source)) {
            $errors['source_path'] = 'System paths cannot be redirected.';
        } elseif (mb_strlen($source) > 500) {
            $errors['source_path'] = 'The source path is too long.';
        } elseif ($this->db->value('SELECT 1 FROM {{redirects}} WHERE source_hash = ? AND id <> ?', [sha1($source), (int) $exceptId])) {
            $errors['source_path'] = 'A redirect for this path already exists.';
        }
        if ($dest === '') {
            $errors['destination'] = 'Enter a destination.';
        } elseif (preg_match('#^https?://#i', $dest)) {
            if (!Validator::isHttpUrl($dest)) {
                $errors['destination'] = 'Enter a valid destination URL.';
            }
        } elseif (!str_starts_with($dest, '/') || str_starts_with($dest, '//') || preg_match('/[\s<>"\\\\]/', $dest)) {
            $errors['destination'] = 'Use a path such as /new-post or a full https:// URL.';
        }
        if (!$errors) {
            $destPath = preg_match('#^https?://#i', $dest) ? null : self::normalize($dest);
            if ($destPath === $source) {
                $errors['destination'] = 'The destination cannot be the same as the source.';
            } elseif ($destPath !== null && $this->wouldLoop($source, $destPath, $exceptId)) {
                $errors['destination'] = 'This redirect would create a loop.';
            }
        }
        return $errors;
    }

    private function wouldLoop(string $source, string $dest, ?int $exceptId): bool
    {
        $all = [];
        foreach ($this->db->all('SELECT id, source_path, destination FROM {{redirects}} WHERE is_enabled = 1') as $r) {
            if ((int) $r['id'] !== (int) $exceptId) {
                $all[$r['source_path']] = $r['destination'];
            }
        }
        $current = $dest;
        for ($i = 0; $i < 20; $i++) {
            if ($current === $source) {
                return true;
            }
            if (!isset($all[$current]) || preg_match('#^https?://#i', $all[$current])) {
                return false;
            }
            $current = self::normalize($all[$current]);
        }
        return true;
    }

    public function save(array $data, ?int $id = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'source_path' => $data['source_path'],
            'source_hash' => sha1($data['source_path']),
            'destination' => $data['destination'],
            'status_code' => in_array((int) $data['status_code'], [301, 302, 307, 308], true) ? (int) $data['status_code'] : 301,
            'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
            'note' => mb_substr((string) ($data['note'] ?? ''), 0, 255) ?: null,
            'updated_at' => $now,
        ];
        if ($id) {
            $this->db->update('redirects', $row, 'id = ?', [$id]);
        } else {
            $id = $this->db->insert('redirects', $row + [
                'is_auto' => !empty($data['is_auto']) ? 1 : 0,
                'created_by' => $this->app->auth->id(),
                'created_at' => $now,
            ]);
        }
        $this->clearCache();
        return $id;
    }

    /**
     * Called when a published post's slug changes. Points old URLs (and redirects that
     * pointed at them) to the new path and removes any redirect occupying the new path.
     */
    public function slugChanged(string $oldPath, string $newPath, string $note): void
    {
        $oldPath = self::normalize($oldPath);
        $newPath = self::normalize($newPath);
        if ($oldPath === $newPath) {
            return;
        }
        $this->db->transaction(function (Database $db) use ($oldPath, $newPath, $note) {
            $db->delete('redirects', 'source_hash = ?', [sha1($newPath)]);
            $db->query('UPDATE {{redirects}} SET destination = ?, updated_at = ? WHERE destination = ?', [$newPath, gmdate('Y-m-d H:i:s'), $oldPath]);
            $existing = $db->value('SELECT id FROM {{redirects}} WHERE source_hash = ?', [sha1($oldPath)]);
            $data = ['source_path' => $oldPath, 'destination' => $newPath, 'status_code' => 301, 'is_enabled' => 1, 'is_auto' => 1, 'note' => $note];
            $this->save($data, $existing ? (int) $existing : null);
        });
        $this->clearCache();
    }

    /** Removes redirects whose source is now a live path (e.g. a new post took the slug). */
    public function releasePath(string $path): void
    {
        if ($this->db->delete('redirects', 'source_hash = ?', [sha1(self::normalize($path))])) {
            $this->clearCache();
        }
    }

    /** Redirects whose destination is itself redirected. */
    public function chains(): array
    {
        $rows = $this->db->all('SELECT * FROM {{redirects}} WHERE is_enabled = 1');
        $bySource = [];
        foreach ($rows as $r) {
            $bySource[$r['source_path']] = $r;
        }
        $out = [];
        foreach ($rows as $r) {
            if (!preg_match('#^https?://#i', $r['destination']) && isset($bySource[self::normalize($r['destination'])])) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Points every chained redirect directly at its final destination. */
    public function flatten(): int
    {
        $rows = $this->db->all('SELECT * FROM {{redirects}} WHERE is_enabled = 1');
        $bySource = [];
        foreach ($rows as $r) {
            $bySource[$r['source_path']] = $r['destination'];
        }
        $changed = 0;
        foreach ($rows as $r) {
            $dest = $r['destination'];
            $seen = [$r['source_path'] => true];
            for ($i = 0; $i < 20 && !preg_match('#^https?://#i', $dest) && isset($bySource[self::normalize($dest)]); $i++) {
                $n = self::normalize($dest);
                if (isset($seen[$n])) {
                    break;
                }
                $seen[$n] = true;
                $dest = $bySource[$n];
            }
            if ($dest !== $r['destination'] && self::normalize($dest) !== $r['source_path']) {
                $this->db->update('redirects', ['destination' => $dest, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
                $changed++;
            }
        }
        $this->clearCache();
        return $changed;
    }
}
