<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Small file cache stored under storage/cache. Values are JSON encoded.
 */
final class Cache
{
    public function __construct(private string $dir)
    {
    }

    public function dir(): string
    {
        return $this->dir;
    }

    private function file(string $key): string
    {
        return $this->dir . '/data/' . sha1($key) . '.json';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data) || (($data['e'] ?? 0) !== 0 && $data['e'] < time())) {
            return $default;
        }
        return $data['v'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        self::writeAtomic($this->file($key), (string) json_encode(['e' => $ttl > 0 ? time() + $ttl : 0, 'v' => $value]));
    }

    public function remember(string $key, int $ttl, callable $fn): mixed
    {
        $hit = $this->get($key, $miss = new \stdClass());
        if ($hit !== $miss) {
            return $hit;
        }
        $value = $fn();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function forget(string $key): void
    {
        @unlink($this->file($key));
    }

    /** Removes cached data and rendered pages. */
    public function flush(): int
    {
        $count = 0;
        foreach (['data', 'pages'] as $sub) {
            $count += self::removeTree($this->dir . '/' . $sub, false);
        }
        return $count;
    }

    public static function writeAtomic(string $file, string $contents): bool
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = $dir . '/.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $file);
    }

    public static function removeTree(string $dir, bool $removeSelf = true): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            } elseif (@unlink($f->getPathname())) {
                $count++;
            }
        }
        if ($removeSelf) {
            @rmdir($dir);
        }
        return $count;
    }
}
