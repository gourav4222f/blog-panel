<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Services\ImageService;
use BlogFlow\Services\Mailer;
use BlogFlow\Services\MenuService;
use BlogFlow\Services\PageCache;
use BlogFlow\Services\RedirectService;
use BlogFlow\Services\Settings;
use BlogFlow\Services\SettingsSchema;

/**
 * Schema-driven settings pages (General, URL, Social, Images, Integrations, Security, SEO groups).
 */
final class SettingsController extends AdminController
{
    private const PAGES = [
        'general' => ['settings/general', 'General settings', 'Blog identity, homepage and article display options.'],
        'url' => ['settings/url', 'URL settings', 'Canonical address and permalink structure.'],
        'social' => ['settings/social', 'Social media', 'Profiles used in the footer and structured data, and sharing buttons.'],
        'images' => ['settings/images', 'Image settings', 'Upload limits, compression and responsive sizes.'],
        'integrations' => ['settings/integrations', 'Integrations', 'Analytics, verification, newsletter and email delivery. Everything here is optional.'],
        'security' => ['settings/security', 'Security settings', 'Login protection, sessions, passwords and role permissions.'],
        'seo' => ['seo/metadata', 'Global metadata', 'Title templates, default descriptions, indexing rules and social defaults.'],
        'schema' => ['seo/schema', 'Schema settings', 'Structured data describing your organisation and articles.'],
        'sitemap' => ['seo/sitemap', 'Sitemap & feed', 'What appears in sitemap.xml and feed.xml.'],
    ];

    public function edit(string $group): Response
    {
        return $this->render($group);
    }

    public function save(string $group): Response
    {
        return $this->store($group);
    }

    public function seo_seo(): Response
    {
        return $this->request->isPost() ? $this->store('seo') : $this->render('seo');
    }

    public function seo_schema(): Response
    {
        return $this->request->isPost() ? $this->store('schema') : $this->render('schema');
    }

    public function seo_sitemap(): Response
    {
        return $this->request->isPost() ? $this->store('sitemap') : $this->render('sitemap');
    }

    private function render(string $group, array $errors = [], ?array $input = null, int $status = 200): Response
    {
        [$path, $title, $intro] = self::PAGES[$group];
        $values = $input ?? $this->app->settings->group($group);
        return $this->page('settings/form', [
            'pageTitle' => $title,
            'title' => $title,
            'intro' => $intro,
            'group' => $group,
            'action' => $this->app->url->admin($path),
            'schema' => SettingsSchema::group($group),
            'values' => $values,
            'errors' => $errors + ($this->app->session->pullOld()['errors'] ?? []),
            'caps' => ImageService::capabilities(),
            'isAdmin' => $this->app->auth->role() === 'admin',
        ], $status);
    }

    private function store(string $group): Response
    {
        $s = $this->app->settings;
        $current = $s->group($group);
        $input = (array) ($this->request->post['values'] ?? []);
        [$clean, $errors] = Settings::validateGroup($group, $input, $current, $this->app->auth->role() === 'admin');

        if ($group === 'url' && !$errors) {
            $clean['blog_url'] = rtrim($clean['blog_url'], '/');
            $bases = [$clean['category_base'], $clean['tag_base'], $clean['author_base']];
            if (count(array_unique($bases)) < 3) {
                $errors['tag_base'] = 'Category, tag and author bases must all be different.';
            }
            foreach (['category_base', 'tag_base', 'author_base'] as $k) {
                if ($clean[$k] !== $current[$k] && ($this->db->value('SELECT 1 FROM {{posts}} WHERE slug = ?', [$clean[$k]]) || in_array($clean[$k], ['admin', 'page', 'search', 'preview', 'assets', 'uploads', 'install', 'feed', 'sitemap'], true))) {
                    $errors[$k] = 'This base conflicts with an existing post or system path.';
                }
            }
        }
        if ($errors) {
            $this->flash('error', 'Please correct the highlighted fields.');
            return $this->render($group, $errors, array_merge($current, $input), 422);
        }

        $rows = [];
        foreach ($clean as $k => $v) {
            $rows[$group . '.' . $k] = $v;
        }
        $s->setMany($rows);

        if ($group === 'url') {
            $this->urlChanged($current, $clean);
        }
        $this->app->cache->flush();
        $this->app->service(MenuService::class)->clearCache();
        PageCache::clear();
        $this->log('settings_update', 'settings', null, 'Updated ' . SettingsSchema::group($group)['title']);
        $this->flash('success', 'Settings saved.');
        return $this->redirect($this->app->url->admin(self::PAGES[$group][0]));
    }

    /** Keeps archive URLs working when bases change. */
    private function urlChanged(array $old, array $new): void
    {
        $redirects = $this->app->service(RedirectService::class);
        $created = 0;
        foreach (['category_base' => 'categories', 'tag_base' => 'tags', 'author_base' => 'authors'] as $key => $table) {
            if ($old[$key] === $new[$key]) {
                continue;
            }
            foreach ($this->db->column('SELECT slug FROM {{' . $table . '}}') as $slug) {
                $redirects->slugChanged('/' . $old[$key] . '/' . $slug, '/' . $new[$key] . '/' . $slug, 'Archive base changed');
                $created++;
            }
        }
        if ($created) {
            $this->flash('info', "$created redirect(s) were created so old archive URLs keep working.");
        }
        if (rtrim($old['blog_url'], '/') !== $new['blog_url']) {
            $this->flash('warning', 'The blog URL changed. Canonical URLs, the sitemap and feeds now use ' . $new['blog_url'] . '. Update your Search Console property and the robots.txt sitemap line if the domain changed.');
        }
    }

    public function testEmail(): Response
    {
        $user = $this->app->auth->user();
        $ok = $this->app->service(Mailer::class)->send($user['email'], 'BlogFlow test email', "This is a test message from your BlogFlow CMS installation at " . $this->app->url->home() . ".\n\nIf you received it, email delivery works.");
        $this->flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $user['email'] . '.' : 'The email could not be sent. Check the SMTP settings and storage/logs for details.');
        return $this->redirect($this->app->url->admin('settings/integrations'));
    }
}
