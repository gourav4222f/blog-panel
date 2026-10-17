<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Validation\Validator;

/**
 * Header/footer navigation menus with internal (blog), main-website and external links.
 */
final class MenuService
{
    public const LOCATIONS = ['header' => 'Header navigation', 'footer' => 'Footer navigation'];
    public const TYPES = [
        'custom' => 'Custom URL',
        'main' => 'Main website',
        'blog' => 'Blog home',
        'category' => 'Blog category',
        'post' => 'Blog post',
        'tag' => 'Blog tag',
    ];

    private Database $db;

    public function __construct(private App $app)
    {
        $this->db = $app->db;
    }

    public function menuId(string $location): int
    {
        $id = $this->db->value('SELECT id FROM {{menus}} WHERE location = ?', [$location]);
        if ($id) {
            return (int) $id;
        }
        return $this->db->insert('menus', ['location' => $location, 'name' => self::LOCATIONS[$location] ?? ucfirst($location)]);
    }

    /** Raw items for the admin builder (flat, ordered). */
    public function items(string $location): array
    {
        return $this->db->all(
            'SELECT i.* FROM {{menu_items}} i INNER JOIN {{menus}} m ON m.id = i.menu_id WHERE m.location = ? ORDER BY i.sort_order, i.id',
            [$location]
        );
    }

    /**
     * Resolved, visible menu tree for rendering.
     *
     * @return array<int, array{label: string, url: string, new_tab: bool, external: bool, active: bool, children: array}>
     */
    public function tree(string $location, string $currentUrl = ''): array
    {
        $items = $this->app->cache->remember('menu:' . $location, 0, fn () => $this->resolveAll($this->items($location)));
        $byParent = [];
        foreach ($items as $item) {
            if (!$item['visible'] || $item['url'] === '') {
                continue;
            }
            $item['active'] = $currentUrl !== '' && rtrim($item['url'], '/') === rtrim($currentUrl, '/');
            $byParent[(int) $item['parent_id']][] = $item;
        }
        $out = [];
        foreach ($byParent[0] ?? [] as $item) {
            $item['children'] = $byParent[$item['id']] ?? [];
            $out[] = $item;
        }
        return $out;
    }

    private function resolveAll(array $items): array
    {
        $url = $this->app->url;
        $ids = ['category' => [], 'post' => [], 'tag' => []];
        foreach ($items as $i) {
            if (isset($ids[$i['link_type']]) && $i['target_id']) {
                $ids[$i['link_type']][] = (int) $i['target_id'];
            }
        }
        $slugs = [];
        foreach (['category' => 'categories', 'tag' => 'tags'] as $type => $table) {
            if ($ids[$type]) {
                foreach ($this->db->all('SELECT id, slug FROM {{' . $table . '}} WHERE id IN (' . Database::placeholders($ids[$type]) . ')', $ids[$type]) as $r) {
                    $slugs[$type][(int) $r['id']] = $r['slug'];
                }
            }
        }
        if ($ids['post']) {
            foreach ($this->db->all("SELECT id, slug FROM {{posts}} WHERE deleted_at IS NULL AND status = 'published' AND id IN (" . Database::placeholders($ids['post']) . ')', $ids['post']) as $r) {
                $slugs['post'][(int) $r['id']] = $r['slug'];
            }
        }
        $main = $this->app->settings->str('general.main_site_url');
        $out = [];
        foreach ($items as $i) {
            $target = (int) $i['target_id'];
            $href = match ($i['link_type']) {
                'blog' => $url->home(),
                'main' => $main !== '' ? rtrim($main, '/') . '/' : '',
                'category' => isset($slugs['category'][$target]) ? $url->category($slugs['category'][$target]) : '',
                'tag' => isset($slugs['tag'][$target]) ? $url->tag($slugs['tag'][$target]) : '',
                'post' => isset($slugs['post'][$target]) ? $url->post($slugs['post'][$target]) : '',
                default => (string) $i['url'],
            };
            $out[] = [
                'id' => (int) $i['id'],
                'parent_id' => (int) ($i['parent_id'] ?? 0),
                'label' => (string) $i['label'],
                'url' => $href,
                'new_tab' => (bool) $i['new_tab'],
                'external' => $href !== '' && $url->isExternal($href),
                'visible' => (bool) $i['is_visible'],
            ];
        }
        return $out;
    }

    public static function validUrl(string $u): bool
    {
        if ($u === '') {
            return false;
        }
        if (str_starts_with($u, '/') && !str_starts_with($u, '//')) {
            return !preg_match('/[\s<>"\\\\]/', $u);
        }
        if (preg_match('/^(mailto|tel):[^\s<>"]+$/i', $u)) {
            return true;
        }
        if (str_starts_with($u, '#')) {
            return (bool) preg_match('/^#[A-Za-z0-9_\-]*$/', $u);
        }
        return Validator::isHttpUrl($u);
    }

    /**
     * Replaces all items of a menu. Items: [{key, parent_key, label, link_type, target_id, url, new_tab, is_visible}]
     *
     * @return array<int, string> errors keyed by item index
     */
    public function save(string $location, array $items): array
    {
        $errors = [];
        $clean = [];
        foreach (array_values($items) as $i => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $type = (string) ($item['link_type'] ?? 'custom');
            $urlValue = trim((string) ($item['url'] ?? ''));
            if ($label === '' || mb_strlen($label) > 120) {
                $errors[$i] = 'Each item needs a label (max 120 characters).';
                continue;
            }
            if (!isset(self::TYPES[$type])) {
                $errors[$i] = 'Invalid link type.';
                continue;
            }
            if ($type === 'custom' && !self::validUrl($urlValue)) {
                $errors[$i] = sprintf('“%s” needs a valid URL (https://…, /path, mailto: or tel:).', $label);
                continue;
            }
            if (in_array($type, ['category', 'post', 'tag'], true) && (int) ($item['target_id'] ?? 0) <= 0) {
                $errors[$i] = sprintf('Choose a target for “%s”.', $label);
                continue;
            }
            $clean[] = [
                'key' => (string) ($item['key'] ?? $i),
                'parent_key' => (string) ($item['parent_key'] ?? ''),
                'label' => $label,
                'link_type' => $type,
                'target_id' => in_array($type, ['category', 'post', 'tag'], true) ? (int) $item['target_id'] : null,
                'url' => $type === 'custom' ? mb_substr($urlValue, 0, 1000) : null,
                'new_tab' => !empty($item['new_tab']) ? 1 : 0,
                'is_visible' => !array_key_exists('is_visible', $item) || !empty($item['is_visible']) ? 1 : 0,
            ];
        }
        if ($errors) {
            return $errors;
        }
        $menuId = $this->menuId($location);
        $this->db->transaction(function (Database $db) use ($menuId, $clean) {
            $db->delete('menu_items', 'menu_id = ?', [$menuId]);
            $idByKey = [];
            $now = gmdate('Y-m-d H:i:s');
            // Insert top-level items first so children can reference their parent's new ID.
            foreach ([true, false] as $topLevel) {
                foreach ($clean as $order => $item) {
                    if (($item['parent_key'] === '') !== $topLevel) {
                        continue;
                    }
                    $parentId = $topLevel ? null : ($idByKey[$item['parent_key']] ?? null);
                    $idByKey[$item['key']] = $db->insert('menu_items', [
                        'menu_id' => $menuId,
                        'parent_id' => $parentId,
                        'label' => $item['label'],
                        'link_type' => $item['link_type'],
                        'target_id' => $item['target_id'],
                        'url' => $item['url'],
                        'new_tab' => $item['new_tab'],
                        'is_visible' => $item['is_visible'],
                        'sort_order' => $order,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
        $this->clearCache();
        return [];
    }

    public function clearCache(): void
    {
        foreach (array_keys(self::LOCATIONS) as $loc) {
            $this->app->cache->forget('menu:' . $loc);
        }
        PageCache::clear();
    }
}
