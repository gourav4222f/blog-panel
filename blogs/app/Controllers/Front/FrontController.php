<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\Controller;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Categories;
use BlogFlow\Services\MediaService;
use BlogFlow\Services\MenuService;
use BlogFlow\Services\SeoService;
use BlogFlow\Services\ThemeService;

/**
 * Shared behaviour for public pages: site chrome, theme and SEO headers.
 */
abstract class FrontController extends Controller
{
    protected function seo(): SeoService
    {
        return $this->app->service(SeoService::class);
    }

    protected function media(): MediaService
    {
        return $this->app->service(MediaService::class);
    }

    protected function page(): int
    {
        return 1;
    }

    /** /page/1 duplicates the first page: send it to the canonical URL. */
    protected function canonicalFirstPage(string $page, callable $url): ?\BlogFlow\Core\Response
    {
        return $page === '1' && str_contains($this->request->path, '/page/') ? \BlogFlow\Core\Response::redirect($url(1), 301) : null;
    }

    /** Validates the page number and returns the page count. */
    protected function pages(int $total, int $perPage, int $page): int
    {
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        if ($page > $pages) {
            throw new HttpException(404);
        }
        return $pages;
    }

    protected function perPage(): int
    {
        return max(1, $this->app->settings->int('general.posts_per_page'));
    }

    /** Data used by the layout on every public page. */
    public function site(): array
    {
        $s = $this->app->settings;
        $theme = $s->group('theme');
        $typography = $s->group('typography');
        $layout = $s->group('layout');
        $media = $this->media();
        $mediaRows = $media->findMany([$theme['logo'], $theme['logo_dark'], $theme['favicon'], $layout['footer_logo']]);
        $fonts = ThemeService::usedFonts($typography);
        $fontsUrl = $this->app->url->basePath() . '/assets/fonts';
        $menus = $this->app->service(MenuService::class);
        $current = $this->app->url->to($this->request->path);
        return [
            'name' => $s->str('general.blog_name') ?: 'Blog',
            'tagline' => $s->str('general.blog_tagline'),
            'lang' => $s->str('general.language') ?: 'en',
            'main_url' => $s->str('general.main_site_url'),
            'main_name' => $s->str('general.main_site_name'),
            'logo' => $mediaRows[(int) $theme['logo']] ?? null,
            'logo_dark' => $mediaRows[(int) $theme['logo_dark']] ?? null,
            'favicon' => $mediaRows[(int) $theme['favicon']] ?? null,
            'footer_logo' => $mediaRows[(int) $layout['footer_logo']] ?? ($mediaRows[(int) $theme['logo']] ?? null),
            'theme' => $theme,
            'layout' => $layout,
            'general' => $s->group('general'),
            'social' => $s->group('social'),
            'integrations' => $s->group('integrations'),
            'css' => ThemeService::fontFaceCss($fonts, $fontsUrl) . ThemeService::variablesCss($theme, $typography),
            'preload_font' => ThemeService::preloadFont($typography) ? $fontsUrl . '/' . ThemeService::preloadFont($typography) : null,
            'body_class' => ThemeService::bodyClasses($theme, $layout),
            'header_menu' => $menus->tree('header', $current),
            'footer_menu' => $menus->tree('footer', $current),
            'categories' => $s->bool('general.show_category_nav') ? $this->app->service(Categories::class)->navigation(10) : [],
            'search_enabled' => $s->bool('general.show_search'),
        ];
    }

    protected function render(string $template, array $data, array $meta, int $status = 200): Response
    {
        $data['meta'] = $meta;
        $data['site'] ??= $this->site();
        $response = $this->view($template, $data, $status);
        if ($this->app->settings->bool('seo.x_robots_header') && empty($meta['index'])) {
            $response->header('X-Robots-Tag', 'noindex' . (str_contains((string) $meta['robots'], 'nofollow') ? ', nofollow' : ''));
        }
        return $response;
    }
}
