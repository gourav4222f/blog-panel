<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\Cache;
use BlogFlow\Core\Database;

/**
 * Publishes scheduled posts without requiring cron: every request compares the
 * current time to a cached "next due" timestamp, and only queries the database when due.
 */
final class Scheduler
{
    private static function file(): string
    {
        return BF_ROOT . '/storage/cache/next-publish.txt';
    }

    public static function tick(Database $db): int
    {
        $file = self::file();
        $next = is_file($file) ? trim((string) @file_get_contents($file)) : '';
        if ($next === 'none' || ($next !== '' && ctype_digit($next) && (int) $next > time())) {
            return 0;
        }
        $now = gmdate('Y-m-d H:i:s');
        $count = $db->query(
            "UPDATE {{posts}} SET status = 'published' WHERE status = 'scheduled' AND deleted_at IS NULL AND published_at <= ?",
            [$now]
        )->rowCount();
        if ($count > 0) {
            PageCache::clear();
        }
        self::refresh($db);
        return $count;
    }

    /** Recomputes the next scheduled publication time. */
    public static function refresh(Database $db): void
    {
        $next = $db->value("SELECT MIN(published_at) FROM {{posts}} WHERE status = 'scheduled' AND deleted_at IS NULL");
        Cache::writeAtomic(self::file(), $next ? (string) strtotime($next . ' UTC') : 'none');
    }
}
