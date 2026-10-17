<?php

declare(strict_types=1);

namespace BlogFlow\Models;

final class Tags extends Terms
{
    protected string $table = 'tags';
    protected string $postColumn = 'id';

    protected function countJoin(): string
    {
        return 'LEFT JOIN {{post_tags}} pt ON pt.tag_id = t.id LEFT JOIN {{posts}} p ON p.id = pt.post_id AND p.deleted_at IS NULL';
    }

    public function publishedCount(int $id): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM {{post_tags}} pt INNER JOIN {{posts}} p ON p.id = pt.post_id WHERE pt.tag_id = ? AND ' . Posts::PUBLIC_WHERE,
            [$id, Posts::now()]
        );
    }

    /** Finds tags by name, creating missing ones. @return int[] ids */
    public function resolveNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
            if ($name === '' || mb_strlen($name) > 120) {
                continue;
            }
            $existing = $this->db->one('SELECT id FROM {{tags}} WHERE name = ? OR slug = ?', [$name, \BlogFlow\Validation\Validator::slugify($name)]);
            $ids[] = $existing ? (int) $existing['id'] : $this->create(['name' => $name, 'slug' => $this->uniqueSlug($name)]);
        }
        return array_values(array_unique($ids));
    }
}
