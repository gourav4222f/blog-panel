<?php

declare(strict_types=1);

namespace BlogFlow\Models;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Validation\Validator;

/**
 * Shared repository logic for categories, tags and authors (slug-addressed records).
 */
abstract class Terms
{
    protected Database $db;
    protected string $table;
    protected string $nameColumn = 'name';
    protected string $postColumn;

    public function __construct(protected App $app)
    {
        $this->db = $app->db;
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM {{' . $this->table . '}} WHERE id = ?', [$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->one('SELECT * FROM {{' . $this->table . '}} WHERE slug = ?', [$slug]);
    }

    public function all(): array
    {
        return $this->db->all('SELECT * FROM {{' . $this->table . '}} ORDER BY ' . $this->nameColumn);
    }

    public function options(): array
    {
        $out = [];
        foreach ($this->all() as $row) {
            $out[(int) $row['id']] = $row[$this->nameColumn];
        }
        return $out;
    }

    public function uniqueSlug(string $source, ?int $exceptId = null): string
    {
        $base = Validator::slugify($source, 100) ?: 'item';
        $slug = $base;
        for ($i = 2; $this->db->value('SELECT 1 FROM {{' . $this->table . '}} WHERE slug = ? AND id <> ?', [$slug, (int) $exceptId]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM {{' . $this->table . '}} WHERE slug = ? AND id <> ?', [$slug, (int) $exceptId]);
    }

    public function create(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update($this->table, $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete($this->table, 'id = ?', [$id]);
    }

    /** All rows with total and published post counts. */
    public function withCounts(string $search = ''): array
    {
        $where = '';
        $params = [Posts::now()];
        if ($search !== '') {
            $where = ' WHERE t.' . $this->nameColumn . ' LIKE ? OR t.slug LIKE ?';
            $like = '%' . Posts::likeEscape($search) . '%';
            array_push($params, $like, $like);
        }
        return $this->db->all(
            'SELECT t.*, COUNT(p.id) AS post_count, SUM(' . Posts::PUBLIC_WHERE . ') AS published_count
             FROM {{' . $this->table . '}} t ' . $this->countJoin() . $where . '
             GROUP BY t.id ORDER BY ' . $this->orderBy(),
            $params
        );
    }

    protected function countJoin(): string
    {
        return 'LEFT JOIN {{posts}} p ON p.' . $this->postColumn . ' = t.id AND p.deleted_at IS NULL';
    }

    protected function orderBy(): string
    {
        return 't.' . $this->nameColumn;
    }

    public function publishedCount(int $id): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM {{posts}} p WHERE p.' . $this->postColumn . ' = ? AND ' . Posts::PUBLIC_WHERE,
            [$id, Posts::now()]
        );
    }
}
