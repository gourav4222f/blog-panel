<?php

declare(strict_types=1);

namespace BlogFlow\Models;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Services\MediaService;

/**
 * Post queries for the public site and admin lists.
 */
final class Posts
{
    public const STATUSES = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'];

    private const CARD_COLUMNS = 'p.id, p.title, p.slug, p.excerpt, p.status, p.published_at, p.content_modified_at, p.updated_at,
        p.reading_time, p.word_count, p.is_featured, p.featured_image_id, p.featured_image_alt, LEFT(p.content_text, 320) AS text_preview,
        c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
        a.id AS author_id, a.display_name AS author_name, a.slug AS author_slug,
        m.id AS m_id, m.path AS m_path, m.width AS m_width, m.height AS m_height, m.variants AS m_variants,
        m.alt_text AS m_alt_text, m.mime AS m_mime';

    private const CARD_JOINS = 'FROM {{posts}} p
        LEFT JOIN {{categories}} c ON c.id = p.category_id
        LEFT JOIN {{authors}} a ON a.id = p.author_id
        LEFT JOIN {{media}} m ON m.id = p.featured_image_id';

    private Database $db;

    public function __construct(private App $app)
    {
        $this->db = $app->db;
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** SQL condition for publicly visible posts; bind self::now(). */
    public const PUBLIC_WHERE = "p.deleted_at IS NULL AND p.status = 'published' AND p.published_at <= ?";

    /** Extracts the joined media columns into a hydrated media row. */
    public static function withImage(array $row): array
    {
        $row['image'] = null;
        if (!empty($row['m_id'])) {
            $row['image'] = MediaService::hydrate([
                'id' => $row['m_id'], 'path' => $row['m_path'], 'width' => $row['m_width'], 'height' => $row['m_height'],
                'variants' => $row['m_variants'], 'alt_text' => $row['m_alt_text'], 'mime' => $row['m_mime'],
            ]);
        }
        foreach (['m_id', 'm_path', 'm_width', 'm_height', 'm_variants', 'm_alt_text', 'm_mime'] as $k) {
            unset($row[$k]);
        }
        return $row;
    }

    public static function likeEscape(string $s): string
    {
        return addcslashes($s, '%_\\');
    }

    /**
     * Public paginated list.
     *
     * @param array $f category_id, tag_id, author_id, q, exclude (int[])
     * @return array{items: array, total: int}
     */
    public function publicList(array $f, int $page, int $perPage): array
    {
        $where = [self::PUBLIC_WHERE];
        $params = [self::now()];
        $join = '';
        if (!empty($f['category_ids'])) {
            $where[] = 'p.category_id IN (' . Database::placeholders($f['category_ids']) . ')';
            array_push($params, ...$f['category_ids']);
        }
        if (!empty($f['tag_id'])) {
            $join .= ' INNER JOIN {{post_tags}} pt ON pt.post_id = p.id AND pt.tag_id = ' . (int) $f['tag_id'];
        }
        if (!empty($f['author_id'])) {
            $where[] = 'p.author_id = ?';
            $params[] = (int) $f['author_id'];
        }
        if (!empty($f['exclude'])) {
            $where[] = 'p.id NOT IN (' . Database::placeholders($f['exclude']) . ')';
            array_push($params, ...array_map('intval', $f['exclude']));
        }
        $order = 'p.published_at DESC, p.id DESC';
        $orderParams = [];
        if (isset($f['q']) && $f['q'] !== '') {
            $like = '%' . self::likeEscape($f['q']) . '%';
            $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content_text LIKE ?)';
            array_push($params, $like, $like, $like);
            $order = '(p.title LIKE ?) DESC, p.published_at DESC';
            $orderParams[] = $like;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {{posts}} p' . $join . ' WHERE ' . $whereSql, $params);
        $rows = $this->db->all(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . $join . ' WHERE ' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?',
            [...$params, ...$orderParams, $perPage, max(0, ($page - 1) * $perPage)]
        );
        return ['items' => array_map([self::class, 'withImage'], $rows), 'total' => $total];
    }

    public function featured(): ?array
    {
        $row = $this->db->one(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . ' WHERE ' . self::PUBLIC_WHERE . ' AND p.is_featured = 1
             ORDER BY p.published_at DESC LIMIT 1',
            [self::now()]
        );
        return $row ? self::withImage($row) : null;
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $row = $this->db->one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    a.display_name AS author_name, a.slug AS author_slug, a.bio AS author_bio, a.job_title AS author_job_title,
                    a.photo_id AS author_photo_id, a.website_url AS author_website, a.social AS author_social
             FROM {{posts}} p
             LEFT JOIN {{categories}} c ON c.id = p.category_id
             LEFT JOIN {{authors}} a ON a.id = p.author_id
             WHERE p.slug = ? AND ' . self::PUBLIC_WHERE,
            [$slug, self::now()]
        );
        return $row;
    }

    /** Full row with joined names (any status), for previews. */
    public function findWithRelations(int $id): ?array
    {
        return $this->db->one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.parent_id AS category_parent_id,
                    a.display_name AS author_name, a.slug AS author_slug, a.bio AS author_bio, a.job_title AS author_job_title,
                    a.photo_id AS author_photo_id, a.website_url AS author_website, a.social AS author_social
             FROM {{posts}} p
             LEFT JOIN {{categories}} c ON c.id = p.category_id
             LEFT JOIN {{authors}} a ON a.id = p.author_id
             WHERE p.id = ?',
            [$id]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM {{posts}} WHERE id = ?', [$id]);
    }

    /** @return array{prev: ?array, next: ?array} */
    public function adjacent(array $post): array
    {
        $cols = 'p.id, p.title, p.slug';
        $prev = $this->db->one(
            "SELECT $cols FROM {{posts}} p WHERE " . self::PUBLIC_WHERE . ' AND (p.published_at < ? OR (p.published_at = ? AND p.id < ?))
             ORDER BY p.published_at DESC, p.id DESC LIMIT 1',
            [self::now(), $post['published_at'], $post['published_at'], $post['id']]
        );
        $next = $this->db->one(
            "SELECT $cols FROM {{posts}} p WHERE " . self::PUBLIC_WHERE . ' AND (p.published_at > ? OR (p.published_at = ? AND p.id > ?))
             ORDER BY p.published_at ASC, p.id ASC LIMIT 1',
            [self::now(), $post['published_at'], $post['published_at'], $post['id']]
        );
        return ['prev' => $prev, 'next' => $next];
    }

    /** Manually selected related posts first, then same category, then shared tags, then latest. */
    public function related(array $post, int $limit): array
    {
        $now = self::now();
        $manual = $this->db->all(
            'SELECT ' . self::CARD_COLUMNS . ' ' . self::CARD_JOINS . '
             INNER JOIN {{post_related}} r ON r.related_id = p.id AND r.post_id = ?
             WHERE ' . self::PUBLIC_WHERE . ' ORDER BY r.sort_order LIMIT ?',
            [$post['id'], $now, $limit]
        );
        $items = $manual;
        $exclude = array_merge([(int) $post['id']], array_map(fn ($r) => (int) $r['id'], $manual));
        if (count($items) < $limit) {
            $more = $this->db->all(
                'SELECT ' . self::CARD_COLUMNS . ',
                    ((p.category_id <=> ?) * 3 + (SELECT COUNT(*) FROM {{post_tags}} t1 INNER JOIN {{post_tags}} t2 ON t2.tag_id = t1.tag_id AND t2.post_id = ?
                     WHERE t1.post_id = p.id)) AS score
                 ' . self::CARD_JOINS . ' WHERE ' . self::PUBLIC_WHERE . ' AND p.id NOT IN (' . Database::placeholders($exclude) . ')
                 ORDER BY score DESC, p.published_at DESC LIMIT ?',
                [$post['category_id'], $post['id'], $now, ...$exclude, $limit - count($items)]
            );
            $items = array_merge($items, $more);
        }
        return array_map([self::class, 'withImage'], $items);
    }

    /** @return array<int, array> post_id => list of tags */
    public function tagsFor(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if (!$postIds) {
            return [];
        }
        $out = [];
        foreach ($this->db->all(
            'SELECT pt.post_id, t.id, t.name, t.slug FROM {{post_tags}} pt INNER JOIN {{tags}} t ON t.id = pt.tag_id
             WHERE pt.post_id IN (' . Database::placeholders($postIds) . ') ORDER BY t.name',
            $postIds
        ) as $row) {
            $out[(int) $row['post_id']][] = $row;
        }
        return $out;
    }

    // ----- Admin -----

    /**
     * @param array $f status (all|draft|scheduled|published|trash|featured), q, category_id, author_id, sort, dir, own_user_id
     */
    public function adminList(array $f, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        $status = $f['status'] ?? 'all';
        if ($status === 'trash') {
            $where[] = 'p.deleted_at IS NOT NULL';
        } else {
            $where[] = 'p.deleted_at IS NULL';
            if (isset(self::STATUSES[$status])) {
                $where[] = 'p.status = ?';
                $params[] = $status;
            } elseif ($status === 'featured') {
                $where[] = 'p.is_featured = 1';
            }
        }
        if (!empty($f['q'])) {
            $like = '%' . self::likeEscape($f['q']) . '%';
            $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
            array_push($params, $like, $like);
        }
        if (!empty($f['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $f['category_id'];
        }
        if (!empty($f['author_id'])) {
            $where[] = 'p.author_id = ?';
            $params[] = (int) $f['author_id'];
        }
        if (!empty($f['own_user_id'])) {
            $where[] = '(p.created_by = ? OR p.author_id = ?)';
            array_push($params, (int) $f['own_user_id'], (int) ($f['own_author_id'] ?? 0));
        }
        $sortable = ['title' => 'p.title', 'date' => 'COALESCE(p.published_at, p.created_at)', 'updated' => 'p.updated_at', 'status' => 'p.status'];
        $sort = $sortable[$f['sort'] ?? 'updated'] ?? 'p.updated_at';
        $dir = strtolower((string) ($f['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $whereSql = implode(' AND ', $where);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {{posts}} p WHERE ' . $whereSql, $params);
        $rows = $this->db->all(
            'SELECT ' . self::CARD_COLUMNS . ', p.created_by, p.deleted_at, p.created_at, p.seo_title, p.meta_description, u.name AS editor_name
             ' . self::CARD_JOINS . ' LEFT JOIN {{users}} u ON u.id = p.updated_by
             WHERE ' . $whereSql . " ORDER BY $sort $dir, p.id DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, max(0, ($page - 1) * $perPage)]
        );
        return ['items' => array_map([self::class, 'withImage'], $rows), 'total' => $total];
    }

    public function counts(?int $ownUserId = null, ?int $ownAuthorId = null): array
    {
        $own = '';
        $params = [];
        if ($ownUserId) {
            $own = ' AND (created_by = ? OR author_id = ?)';
            $params = [$ownUserId, (int) $ownAuthorId];
        }
        $row = $this->db->one(
            "SELECT
                SUM(deleted_at IS NULL) AS total,
                SUM(deleted_at IS NULL AND status = 'published') AS published,
                SUM(deleted_at IS NULL AND status = 'draft') AS draft,
                SUM(deleted_at IS NULL AND status = 'scheduled') AS scheduled,
                SUM(deleted_at IS NULL AND is_featured = 1) AS featured,
                SUM(deleted_at IS NOT NULL) AS trash
             FROM {{posts}} WHERE 1 = 1" . $own,
            $params
        ) ?? [];
        return array_map('intval', $row + ['total' => 0, 'published' => 0, 'draft' => 0, 'scheduled' => 0, 'featured' => 0, 'trash' => 0]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM {{posts}} WHERE slug = ? AND id <> ?', [$slug, (int) $exceptId]);
    }
}
