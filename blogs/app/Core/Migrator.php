<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Applies SQL files in database/migrations in filename order and records them.
 */
final class Migrator
{
    public function __construct(private Database $db, private string $dir)
    {
    }

    public function ensureTable(): void
    {
        $this->db->query('CREATE TABLE IF NOT EXISTS {{migrations}} (
            version VARCHAR(100) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** @return string[] */
    public function available(): array
    {
        $files = glob(rtrim($this->dir, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return array_map(fn ($f) => basename($f, '.sql'), $files);
    }

    /** @return string[] */
    public function applied(): array
    {
        $this->ensureTable();
        return $this->db->column('SELECT version FROM {{migrations}} ORDER BY version');
    }

    /** @return string[] */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    /** @return string[] versions applied */
    public function migrate(): array
    {
        $done = [];
        foreach ($this->pending() as $version) {
            $sql = (string) file_get_contents($this->dir . '/' . $version . '.sql');
            foreach (self::split($sql) as $statement) {
                $this->db->query($statement);
            }
            $this->db->insert('migrations', ['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
            $done[] = $version;
        }
        return $done;
    }

    /** Splits a migration file into statements (statements end with ";" at end of line). */
    public static function split(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        $parts = preg_split('/;\s*$/m', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }
}
