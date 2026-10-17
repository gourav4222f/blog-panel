<?php

declare(strict_types=1);

namespace BlogFlow\Install;

use BlogFlow\Core\Database;
use BlogFlow\Core\Migrator;
use BlogFlow\Services\ThemeService;
use BlogFlow\Validation\Validator;

/**
 * Creates the schema and the initial records for a fresh installation.
 */
final class Seeder
{
    /**
     * @param array $site blog_url, main_site_url, main_site_name, blog_name, timezone, language, preset
     * @param array $admin name, email, password
     */
    public static function install(Database $db, array $site, array $admin): int
    {
        $migrator = new Migrator($db, BF_ROOT . '/database/migrations');
        $migrator->migrate();

        return $db->transaction(function (Database $db) use ($site, $admin) {
            $now = gmdate('Y-m-d H:i:s');
            $userId = $db->insert('users', [
                'name' => $admin['name'],
                'email' => strtolower($admin['email']),
                'password_hash' => password_hash($admin['password'], PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $db->insert('authors', [
                'user_id' => $userId,
                'name' => $admin['name'],
                'display_name' => $admin['name'],
                'slug' => Validator::slugify($admin['name'], 80) ?: 'author',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $db->insert('categories', [
                'name' => 'General', 'slug' => 'general', 'description' => null, 'sort_order' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $preset = ThemeService::PRESETS[$site['preset']] ?? ThemeService::PRESETS['minimal'];
            $settings = [
                'url.blog_url' => rtrim($site['blog_url'], '/'),
                'general.blog_name' => $site['blog_name'],
                'general.main_site_url' => $site['main_site_url'],
                'general.main_site_name' => $site['main_site_name'],
                'general.timezone' => $site['timezone'],
                'general.language' => $site['language'],
                'theme.preset' => $site['preset'],
            ];
            foreach ($preset['theme'] as $k => $v) {
                $settings['theme.' . $k] = $v;
            }
            foreach ($preset['typography'] as $k => $v) {
                $settings['typography.' . $k] = $v;
            }
            foreach ($settings as $name => $value) {
                $db->insert('settings', ['name' => $name, 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => $now]);
            }

            foreach (['header' => 'Header navigation', 'footer' => 'Footer navigation'] as $location => $name) {
                $menuId = $db->insert('menus', ['location' => $location, 'name' => $name]);
                $order = 0;
                if ($site['main_site_url'] !== '') {
                    $db->insert('menu_items', ['menu_id' => $menuId, 'label' => 'Home', 'link_type' => 'main', 'sort_order' => $order++, 'created_at' => $now, 'updated_at' => $now]);
                }
                $db->insert('menu_items', ['menu_id' => $menuId, 'label' => 'Blog', 'link_type' => 'blog', 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]);
            }
            $db->insert('activity_logs', ['user_id' => $userId, 'action' => 'install', 'summary' => 'BlogFlow CMS installed', 'created_at' => $now]);
            return $userId;
        });
    }
}
