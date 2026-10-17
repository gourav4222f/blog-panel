<?php

declare(strict_types=1);

namespace BlogFlow\Helpers;

use BlogFlow\Core\App;

/**
 * Admin sidebar definition. Items are filtered by permission and marked active by path.
 */
final class AdminNav
{
    public static function items(App $app): array
    {
        $groups = [
            ['label' => null, 'items' => [
                ['Dashboard', '', 'dashboard', 'dashboard', []],
            ]],
            ['label' => 'Content', 'items' => [
                ['Posts', 'posts', 'file', 'posts.view', [
                    ['All posts', 'posts', 'posts.view'],
                    ['Add new post', 'posts/new', 'posts.create'],
                    ['Drafts', 'posts?status=draft', 'posts.view'],
                    ['Scheduled', 'posts?status=scheduled', 'posts.view'],
                    ['Trash', 'posts?status=trash', 'posts.view'],
                ]],
                ['Categories', 'categories', 'folder', 'taxonomy.manage', []],
                ['Tags', 'tags', 'tag', 'taxonomy.manage', []],
                ['Authors', 'authors', 'user', 'authors.manage', []],
                ['Media library', 'media', 'image', 'media.view', []],
            ]],
            ['label' => 'Optimise', 'items' => [
                ['SEO', 'seo', 'seo', 'seo.view', [
                    ['SEO overview', 'seo', 'seo.view'],
                    ['SEO audit', 'seo/audit', 'seo.view'],
                    ['Global metadata', 'seo/metadata', 'settings.manage'],
                    ['Schema settings', 'seo/schema', 'settings.manage'],
                    ['Sitemap & feed', 'seo/sitemap', 'settings.manage'],
                    ['Redirects', 'seo/redirects', 'redirects.manage'],
                    ['Link audit', 'seo/links', 'seo.manage'],
                ]],
                ['Appearance', 'appearance/theme', 'palette', 'appearance.manage', [
                    ['Theme settings', 'appearance/theme', 'appearance.manage'],
                    ['Typography', 'appearance/typography', 'appearance.manage'],
                    ['Header & footer', 'appearance/layout', 'appearance.manage'],
                    ['Menu builder', 'appearance/menus', 'appearance.manage'],
                ]],
            ]],
            ['label' => 'System', 'items' => [
                ['Settings', 'settings/general', 'settings', 'settings.manage', [
                    ['General', 'settings/general', 'settings.manage'],
                    ['URL settings', 'settings/url', 'settings.manage'],
                    ['Social media', 'settings/social', 'settings.manage'],
                    ['Image settings', 'settings/images', 'settings.manage'],
                    ['Integrations', 'settings/integrations', 'settings.manage'],
                    ['Users & roles', 'users', 'users.manage'],
                    ['Security', 'security', 'settings.manage'],
                    ['Backup & restore', 'backup', 'backup.manage'],
                ]],
                ['Activity log', 'activity', 'history', 'activity.view', []],
            ]],
        ];

        $path = $app->request->path;
        $query = $app->request->rawQuery();
        $current = trim(substr($path, strlen('/admin')), '/') . ($query !== '' ? '?' . $query : '');
        $currentPath = trim(substr($path, strlen('/admin')), '/');
        $out = [];
        foreach ($groups as $g) {
            $items = [];
            foreach ($g['items'] as [$label, $href, $icon, $perm, $children]) {
                if (!$app->auth->can($perm)) {
                    continue;
                }
                $kids = [];
                foreach ($children as [$cLabel, $cHref, $cPerm]) {
                    if ($app->auth->can($cPerm)) {
                        $kids[] = ['label' => $cLabel, 'href' => $app->url->admin($cHref), 'active' => self::matches($cHref, $current, $currentPath)];
                    }
                }
                $prefix = explode('/', explode('?', $href)[0])[0];
                $active = $href === '' ? $currentPath === '' : (str_starts_with($currentPath, $prefix) || (bool) array_filter($kids, fn ($k) => $k['active']));
                if ($label === 'Settings') {
                    $active = (bool) array_filter($kids, fn ($k) => $k['active']) || str_starts_with($currentPath, 'settings');
                }
                $items[] = ['label' => $label, 'href' => $app->url->admin($href), 'icon' => $icon, 'active' => $active, 'children' => $kids];
            }
            if ($items) {
                $out[] = ['label' => $g['label'], 'items' => $items];
            }
        }
        return $out;
    }

    private static function matches(string $href, string $current, string $currentPath): bool
    {
        if (str_contains($href, '?')) {
            return $current === $href;
        }
        if ($href === 'posts') {
            return $currentPath === 'posts' && !str_contains($current, 'status=');
        }
        return $currentPath === $href || str_starts_with($currentPath, $href . '/');
    }
}
