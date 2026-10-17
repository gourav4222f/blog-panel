<?php

declare(strict_types=1);

namespace BlogFlow\Models;

final class Categories extends Terms
{
    protected string $table = 'categories';
    protected string $postColumn = 'category_id';

    public function all(): array
    {
        return $this->db->all('SELECT * FROM {{categories}} ORDER BY sort_order, name');
    }

    protected function orderBy(): string
    {
        return 't.sort_order, t.name';
    }

    /** Rows ordered as a tree with a "depth" key. */
    public function tree(?array $rows = null): array
    {
        $rows ??= $this->all();
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $ids = array_flip(array_map(fn ($r) => (int) $r['id'], $rows));
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent) {
            foreach ($byParent[$parent] ?? [] as $r) {
                $r['depth'] = $depth;
                $out[] = $r;
                if ($depth < 5) {
                    $walk((int) $r['id'], $depth + 1);
                }
            }
        };
        $walk(0, 0);
        // Orphans whose parent no longer exists.
        foreach ($rows as $r) {
            if ($r['parent_id'] && !isset($ids[(int) $r['parent_id']]) && !in_array($r, $out, true)) {
                $r['depth'] = 0;
                $out[] = $r;
            }
        }
        return $out;
    }

    public function options(): array
    {
        $out = [];
        foreach ($this->tree() as $r) {
            $out[(int) $r['id']] = str_repeat('— ', (int) $r['depth']) . $r['name'];
        }
        return $out;
    }

    /** IDs of a category and all its descendants. */
    public function descendantIds(int $id): array
    {
        $rows = $this->db->all('SELECT id, parent_id FROM {{categories}}');
        $children = [];
        foreach ($rows as $r) {
            $children[(int) ($r['parent_id'] ?? 0)][] = (int) $r['id'];
        }
        $out = [$id];
        $queue = [$id];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($children[$current] ?? [] as $child) {
                if (!in_array($child, $out, true)) {
                    $out[] = $child;
                    $queue[] = $child;
                }
            }
        }
        return $out;
    }

    /** Ancestors from the root down to the direct parent. */
    public function ancestors(array $category): array
    {
        $out = [];
        $seen = [(int) $category['id'] => true];
        $parentId = (int) ($category['parent_id'] ?? 0);
        while ($parentId && !isset($seen[$parentId]) && count($out) < 6) {
            $parent = $this->find($parentId);
            if (!$parent) {
                break;
            }
            $seen[$parentId] = true;
            array_unshift($out, $parent);
            $parentId = (int) ($parent['parent_id'] ?? 0);
        }
        return $out;
    }

    /** Categories with at least one published post (for public navigation). */
    public function navigation(int $limit = 12): array
    {
        return $this->db->all(
            'SELECT c.id, c.name, c.slug, COUNT(p.id) AS published_count FROM {{categories}} c
             INNER JOIN {{posts}} p ON p.category_id = c.id AND ' . Posts::PUBLIC_WHERE . '
             GROUP BY c.id ORDER BY c.sort_order, c.name LIMIT ?',
            [Posts::now(), $limit]
        );
    }
}
