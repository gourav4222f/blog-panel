<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\Cache;
use BlogFlow\Core\Request;
use BlogFlow\Core\Response;

/**
 * Full-page file cache for anonymous public GET requests.
 * Any content or settings change calls PageCache::clear().
 */
final class PageCache
{
    private const HEADERS = ['Content-Type', 'X-Robots-Tag', 'Link', 'Last-Modified'];

    public static function dir(): string
    {
        return BF_ROOT . '/storage/cache/pages';
    }

    public static function key(Request $request): string
    {
        $query = $request->query;
        ksort($query);
        return sha1($request->path . '?' . http_build_query($query));
    }

    private static function file(string $key): string
    {
        return self::dir() . '/' . substr($key, 0, 2) . '/' . $key . '.cache';
    }

    public static function fetch(Request $request, int $ttl): ?Response
    {
        $file = self::file(self::key($request));
        if (!is_file($file) || filemtime($file) < time() - $ttl) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || !str_contains($raw, "\n")) {
            return null;
        }
        [$meta, $body] = explode("\n", $raw, 2);
        $meta = json_decode($meta, true);
        if (!is_array($meta)) {
            return null;
        }
        $response = new Response($body, (int) ($meta['status'] ?? 200), $meta['headers'] ?? []);
        $response->header('X-BlogFlow-Cache', 'HIT');
        return $response;
    }

    public static function store(Request $request, Response $response): void
    {
        if ($response->status() !== 200) {
            return;
        }
        $headers = array_intersect_key($response->headers(), array_flip(self::HEADERS));
        $meta = json_encode(['status' => 200, 'headers' => $headers, 'at' => time()]);
        Cache::writeAtomic(self::file(self::key($request)), $meta . "\n" . $response->body());
        $response->header('X-BlogFlow-Cache', 'MISS');
    }

    public static function clear(): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        // Rename first so concurrent readers never see a half-deleted tree.
        $trash = dirname($dir) . '/pages-old-' . bin2hex(random_bytes(4));
        if (@rename($dir, $trash)) {
            Cache::removeTree($trash);
        } else {
            Cache::removeTree($dir, false);
        }
    }
}
