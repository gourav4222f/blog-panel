<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use BlogFlow\Controllers\Admin as A;
use BlogFlow\Controllers\Front as F;

/**
 * Route table. Public archive bases come from URL settings.
 */
final class Routes
{
    public static function build(Url $url): Router
    {
        $r = new Router();
        $public = ['cache' => true];

        // ----- Admin -----
        $r->group('/admin', [], function (Router $r) {
            $r->get('/login', [A\AuthController::class, 'loginForm']);
            $r->post('/login', [A\AuthController::class, 'login']);
            $r->post('/logout', [A\AuthController::class, 'logout']);
            $r->get('/forgot', [A\AuthController::class, 'forgotForm']);
            $r->post('/forgot', [A\AuthController::class, 'forgot']);
            $r->get('/reset/{token:[a-f0-9]+}', [A\AuthController::class, 'resetForm']);
            $r->post('/reset/{token:[a-f0-9]+}', [A\AuthController::class, 'reset']);

            $auth = fn (string $perm) => ['auth' => true, 'perm' => $perm];

            $r->get('', [A\DashboardController::class, 'index'], $auth('dashboard'));

            // Posts
            $r->get('/posts', [A\PostController::class, 'index'], $auth('posts.view'));
            $r->get('/posts/new', [A\PostController::class, 'create'], $auth('posts.create'));
            $r->post('/posts', [A\PostController::class, 'store'], $auth('posts.create'));
            $r->post('/posts/bulk', [A\PostController::class, 'bulk'], $auth('posts.view'));
            $r->post('/posts/autosave', [A\PostController::class, 'autosave'], $auth('posts.create'));
            $r->get('/posts/export', [A\PostController::class, 'export'], $auth('posts.edit_all'));
            $r->post('/posts/import', [A\PostController::class, 'import'], $auth('posts.edit_all'));
            $r->get('/posts/{id:\d+}/edit', [A\PostController::class, 'edit'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}', [A\PostController::class, 'update'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}/trash', [A\PostController::class, 'trash'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}/restore', [A\PostController::class, 'restore'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}/destroy', [A\PostController::class, 'destroy'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}/duplicate', [A\PostController::class, 'duplicate'], $auth('posts.create'));
            $r->get('/posts/{id:\d+}/revisions', [A\RevisionController::class, 'index'], $auth('posts.view'));
            $r->get('/posts/{id:\d+}/revisions/{rid:\d+}', [A\RevisionController::class, 'show'], $auth('posts.view'));
            $r->post('/posts/{id:\d+}/revisions/{rid:\d+}/restore', [A\RevisionController::class, 'restore'], $auth('posts.view'));

            // Admin JSON APIs
            $r->post('/api/seo-analyze', [A\ApiController::class, 'seoAnalyze'], $auth('posts.view'));
            $r->get('/api/posts', [A\ApiController::class, 'posts'], $auth('posts.view'));
            $r->get('/api/slug', [A\ApiController::class, 'slug'], $auth('posts.view'));
            $r->get('/api/media', [A\MediaController::class, 'apiList'], $auth('media.view'));
            $r->get('/api/media/{id:\d+}', [A\MediaController::class, 'apiShow'], $auth('media.view'));

            // Taxonomies & authors
            foreach (['categories' => A\CategoryController::class, 'tags' => A\TagController::class, 'authors' => A\AuthorController::class] as $base => $class) {
                $perm = $base === 'authors' ? 'authors.manage' : 'taxonomy.manage';
                $r->get('/' . $base, [$class, 'index'], $auth($perm));
                $r->get('/' . $base . '/new', [$class, 'create'], $auth($perm));
                $r->post('/' . $base, [$class, 'store'], $auth($perm));
                $r->get('/' . $base . '/{id:\d+}/edit', [$class, 'edit'], $auth($perm));
                $r->post('/' . $base . '/{id:\d+}', [$class, 'update'], $auth($perm));
                $r->post('/' . $base . '/{id:\d+}/delete', [$class, 'delete'], $auth($perm));
            }
            $r->post('/categories/reorder', [A\CategoryController::class, 'reorder'], $auth('taxonomy.manage'));

            // Media
            $r->get('/media', [A\MediaController::class, 'index'], $auth('media.view'));
            $r->post('/media/upload', [A\MediaController::class, 'upload'], $auth('media.upload'));
            $r->get('/media/optimize', [A\MediaController::class, 'optimizePage'], $auth('media.manage'));
            $r->post('/media/optimize/batch', [A\MediaController::class, 'optimizeBatch'], $auth('media.manage'));
            $r->get('/media/cleanup', [A\MediaController::class, 'cleanup'], $auth('media.manage'));
            $r->post('/media/cleanup', [A\MediaController::class, 'cleanupRun'], $auth('media.manage'));
            $r->get('/media/{id:\d+}', [A\MediaController::class, 'edit'], $auth('media.view'));
            $r->post('/media/{id:\d+}', [A\MediaController::class, 'update'], $auth('media.upload'));
            $r->post('/media/{id:\d+}/replace', [A\MediaController::class, 'replace'], $auth('media.upload'));
            $r->post('/media/{id:\d+}/optimize', [A\MediaController::class, 'optimizeOne'], $auth('media.upload'));
            $r->post('/media/{id:\d+}/delete', [A\MediaController::class, 'delete'], $auth('media.upload'));

            // SEO
            $r->get('/seo', [A\SeoController::class, 'overview'], $auth('seo.view'));
            $r->get('/seo/audit', [A\SeoController::class, 'audit'], $auth('seo.view'));
            $r->post('/seo/audit', [A\SeoController::class, 'runAudit'], $auth('seo.view'));
            $r->get('/seo/links', [A\SeoController::class, 'links'], $auth('seo.manage'));
            $r->post('/seo/links/check', [A\SeoController::class, 'checkLinks'], $auth('seo.manage'));
            $r->get('/seo/redirects', [A\RedirectController::class, 'index'], $auth('redirects.manage'));
            $r->post('/seo/redirects', [A\RedirectController::class, 'store'], $auth('redirects.manage'));
            $r->post('/seo/redirects/flatten', [A\RedirectController::class, 'flatten'], $auth('redirects.manage'));
            $r->post('/seo/redirects/{id:\d+}', [A\RedirectController::class, 'update'], $auth('redirects.manage'));
            $r->post('/seo/redirects/{id:\d+}/toggle', [A\RedirectController::class, 'toggle'], $auth('redirects.manage'));
            $r->post('/seo/redirects/{id:\d+}/delete', [A\RedirectController::class, 'delete'], $auth('redirects.manage'));
            foreach (['metadata' => 'seo', 'schema' => 'schema', 'sitemap' => 'sitemap'] as $slug => $group) {
                $r->get('/seo/' . $slug, [A\SettingsController::class, 'seo_' . $group], $auth('settings.manage'));
                $r->post('/seo/' . $slug, [A\SettingsController::class, 'seo_' . $group], $auth('settings.manage'));
            }

            // Appearance
            $r->get('/appearance/theme', [A\AppearanceController::class, 'theme'], $auth('appearance.manage'));
            $r->post('/appearance/theme', [A\AppearanceController::class, 'theme'], $auth('appearance.manage'));
            $r->post('/appearance/preset', [A\AppearanceController::class, 'preset'], $auth('appearance.manage'));
            $r->get('/appearance/typography', [A\AppearanceController::class, 'typography'], $auth('appearance.manage'));
            $r->post('/appearance/typography', [A\AppearanceController::class, 'typography'], $auth('appearance.manage'));
            $r->get('/appearance/layout', [A\AppearanceController::class, 'layout'], $auth('appearance.manage'));
            $r->post('/appearance/layout', [A\AppearanceController::class, 'layout'], $auth('appearance.manage'));
            $r->get('/appearance/menus', [A\MenuController::class, 'index'], $auth('appearance.manage'));
            $r->post('/appearance/menus/{location:[a-z_]+}', [A\MenuController::class, 'save'], $auth('appearance.manage'));

            // Settings
            $r->get('/settings/{group:general|url|social|images|integrations|security}', [A\SettingsController::class, 'edit'], $auth('settings.manage'));
            $r->post('/settings/{group:general|url|social|images|integrations|security}', [A\SettingsController::class, 'save'], $auth('settings.manage'));
            $r->post('/settings/test-email', [A\SettingsController::class, 'testEmail'], $auth('settings.manage'));

            // Users
            $r->get('/users', [A\UserController::class, 'index'], $auth('users.manage'));
            $r->get('/users/new', [A\UserController::class, 'create'], $auth('users.manage'));
            $r->post('/users', [A\UserController::class, 'store'], $auth('users.manage'));
            $r->get('/users/{id:\d+}/edit', [A\UserController::class, 'edit'], $auth('users.manage'));
            $r->post('/users/{id:\d+}', [A\UserController::class, 'update'], $auth('users.manage'));
            $r->post('/users/{id:\d+}/delete', [A\UserController::class, 'delete'], $auth('users.manage'));
            $r->get('/profile', [A\UserController::class, 'profile'], $auth('dashboard'));
            $r->post('/profile', [A\UserController::class, 'saveProfile'], $auth('dashboard'));
            $r->get('/security', [A\UserController::class, 'security'], $auth('settings.manage'));
            $r->get('/activity', [A\DashboardController::class, 'activity'], $auth('activity.view'));

            // Backup & maintenance
            $r->get('/backup', [A\BackupController::class, 'index'], $auth('backup.manage'));
            $r->post('/backup/database', [A\BackupController::class, 'createDatabase'], $auth('backup.manage'));
            $r->get('/backup/download/{file:[A-Za-z0-9_.\-]+}', [A\BackupController::class, 'download'], $auth('backup.manage'));
            $r->post('/backup/delete/{file:[A-Za-z0-9_.\-]+}', [A\BackupController::class, 'delete'], $auth('backup.manage'));
            $r->post('/backup/restore', [A\BackupController::class, 'restore'], $auth('backup.manage'));
            $r->post('/backup/media', [A\BackupController::class, 'media'], $auth('backup.manage'));
            $r->post('/backup/settings-export', [A\BackupController::class, 'exportSettings'], $auth('backup.manage'));
            $r->post('/backup/settings-import', [A\BackupController::class, 'importSettings'], $auth('backup.manage'));
            $r->post('/maintenance/{action:[a-z\-]+}', [A\BackupController::class, 'maintenance'], $auth('backup.manage'));
        });

        // ----- Public -----
        $r->get('/', [F\HomeController::class, 'index'], $public);
        $r->get('/page/{page:[1-9]\d*}', [F\HomeController::class, 'index'], $public);
        $r->get('/search', [F\SearchController::class, 'index'], $public);
        $r->get('/feed.xml', [F\FeedController::class, 'rss'], $public);
        $r->get('/sitemap.xml', [F\SitemapController::class, 'index'], $public);
        $r->get('/sitemap-{type:home|posts|categories|tags|authors}-{n:[1-9]\d*}.xml', [F\SitemapController::class, 'part'], $public);
        $r->get('/preview/{id:\d+}', [F\PostController::class, 'preview'], ['session' => true]);
        $r->get('/__bf/rewrite-check', [F\HomeController::class, 'rewriteCheck']);

        foreach ([[$url->categoryBase(), 'category'], [$url->tagBase(), 'tag'], [$url->authorBase(), 'author']] as [$base, $method]) {
            $b = preg_quote($base, '#');
            $r->get('/' . $b . '/{slug:[a-z0-9\-]+}', [F\ArchiveController::class, $method], $public);
            $r->get('/' . $b . '/{slug:[a-z0-9\-]+}/page/{page:[1-9]\d*}', [F\ArchiveController::class, $method], $public);
        }
        $r->get('/{slug:[a-z0-9\-]+}', [F\PostController::class, 'show'], $public);

        return $r;
    }

    /** Slugs that cannot be used for posts because they collide with routes or directories. */
    public static function reservedSlugs(Url $url): array
    {
        return array_unique([
            'admin', 'install', 'assets', 'uploads', 'storage', 'app', 'config', 'database', 'vendor', 'bin', 'tests',
            'page', 'search', 'feed', 'rss', 'sitemap', 'preview', 'api', 'login', 'logout', 'wp-admin', 'wp-login',
            'index', 'robots', 'favicon', '__bf',
            $url->categoryBase(), $url->tagBase(), $url->authorBase(),
        ]);
    }
}
