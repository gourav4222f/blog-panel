<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Models\Authors;

/**
 * Builds page metadata: titles, descriptions, canonical URLs, robots directives,
 * Open Graph / X cards and JSON-LD structured data.
 */
final class SeoService
{
    public function __construct(private App $app)
    {
    }

    private function s(string $key): mixed
    {
        return $this->app->settings->get($key);
    }

    public function blogName(): string
    {
        return $this->app->settings->str('general.blog_name') ?: 'Blog';
    }

    public function siteName(): string
    {
        return $this->app->settings->str('general.main_site_name') ?: $this->blogName();
    }

    /** Replaces {variables} in a title/description template and tidies separators. */
    public function template(string $tpl, array $vars): string
    {
        $vars += [
            'site_name' => $this->siteName(),
            'blog_name' => $this->blogName(),
            'sep' => $this->app->settings->str('seo.title_separator') ?: '|',
        ];
        $out = (string) preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '', $tpl);
        $out = trim((string) preg_replace('/\s+/u', ' ', $out));
        // Remove separators left dangling by empty variables, e.g. " | " at either end.
        $out = (string) preg_replace('/^(?:[|\-–—:·•]\s*)+|(?:\s*[|\-–—:·•])+$/u', '', $out);
        return trim($out);
    }

    private function paged(string $title, int $page): string
    {
        if ($page < 2) {
            return $title;
        }
        $suffix = $this->template((string) $this->s('seo.title_paged'), ['page_number' => $page]);
        $suffix = $suffix === '' ? 'Page ' . $page : $suffix;
        $sep = ' ' . ($this->app->settings->str('seo.title_separator') ?: '|') . ' ';
        $pos = mb_strrpos($title, $sep);
        // Insert before the final " | Site" segment when present.
        return $pos !== false
            ? mb_substr($title, 0, $pos) . ' ' . ltrim($suffix, ' ') . mb_substr($title, $pos)
            : $title . ' ' . ltrim($suffix, ' ');
    }

    /** Builds the robots meta content. */
    public function robots(bool $index, bool $follow = true, string $extra = ''): string
    {
        $parts = [$index ? 'index' : 'noindex', $follow ? 'follow' : 'nofollow'];
        if ($index) {
            $preview = (string) $this->s('seo.max_image_preview');
            if (in_array($preview, ['large', 'standard', 'none'], true)) {
                $parts[] = 'max-image-preview:' . $preview;
            }
            $parts[] = 'max-snippet:' . (int) $this->s('seo.max_snippet');
            $parts[] = 'max-video-preview:' . (int) $this->s('seo.max_video_preview');
        }
        foreach (self::parseAdvancedRobots($extra) as $directive) {
            $name = explode(':', $directive)[0];
            $parts = array_values(array_filter($parts, fn ($p) => explode(':', $p)[0] !== $name));
            $parts[] = $directive;
        }
        return implode(', ', $parts);
    }

    /** Validates per-post advanced robots directives (allowlist). */
    public static function parseAdvancedRobots(string $value): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', strtolower(trim($value))) ?: [] as $d) {
            if ($d === '') {
                continue;
            }
            if (in_array($d, ['nosnippet', 'noimageindex', 'notranslate', 'noarchive'], true)
                || preg_match('/^max-snippet:-?\d{1,4}$/', $d)
                || preg_match('/^max-video-preview:-?\d{1,4}$/', $d)
                || preg_match('/^max-image-preview:(none|standard|large)$/', $d)
                || preg_match('/^unavailable_after:\d{4}-\d{2}-\d{2}$/', $d)) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    private function base(string $title, string $description, string $canonical, bool $index, string $type = 'website'): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $this->robots($index),
            'index' => $index,
            'og' => [
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'url' => $canonical,
                'image' => $this->defaultImage(),
            ],
            'twitter_card' => (string) $this->s('seo.twitter_card') ?: 'summary_large_image',
            'schema' => [],
            'prev' => null,
            'next' => null,
            'article' => null,
        ];
    }

    private function defaultImage(): ?array
    {
        $id = (int) $this->s('seo.og_default_image');
        $row = $id ? $this->app->service(MediaService::class)->find($id) : null;
        if (!$row) {
            return null;
        }
        $img = $this->app->service(MediaService::class)->socialImage($row);
        $img['alt'] = (string) ($row['alt_text'] ?? '');
        return $img;
    }

    private function imageFor(?array $media, string $alt = ''): ?array
    {
        if (!$media) {
            return null;
        }
        $img = $this->app->service(MediaService::class)->socialImage($media);
        $img['alt'] = $alt !== '' ? $alt : (string) ($media['alt_text'] ?? '');
        return $img;
    }

    private function pagination(array &$meta, int $page, int $pages, callable $urlFor): void
    {
        $meta['prev'] = $page > 1 ? $urlFor($page - 1) : null;
        $meta['next'] = $page < $pages ? $urlFor($page + 1) : null;
        if ($page > 1 && !$this->app->settings->bool('seo.index_paginated')) {
            $meta['index'] = false;
            $meta['robots'] = $this->robots(false);
        }
    }

    // ----- Page types -----

    public function home(int $page, int $pages): array
    {
        $url = $this->app->url;
        $title = $this->app->settings->str('seo.site_title');
        if ($title === '') {
            $title = $this->blogName() . ($this->siteName() !== $this->blogName() ? ' | ' . $this->siteName() : '');
        }
        $description = $this->app->settings->str('seo.site_description') ?: $this->app->settings->str('general.intro_text');
        $meta = $this->base($this->paged($title, $page), $description, $url->home($page), true);
        $this->pagination($meta, $page, $pages, fn ($p) => $url->home($p));
        $graph = [$this->organization(), $this->webPage($meta, 'CollectionPage')];
        if ($this->app->settings->bool('schema.website')) {
            $graph[] = $this->website();
        }
        $meta['schema'] = $graph;
        return $meta;
    }

    public function category(array $cat, array $ancestors, int $page, int $pages, bool $hasPosts): array
    {
        $url = $this->app->url;
        $title = $cat['seo_title'] ?: $this->template((string) $this->s('seo.title_category'), ['category_name' => $cat['name'], 'page_number' => $page]);
        $desc = $cat['seo_description'] ?: $this->template((string) $this->s('seo.description_category'), [
            'category_description' => HtmlSanitizer::summarize((string) $cat['description'], 160),
            'category_name' => $cat['name'],
        ]);
        $index = $this->termIndexable($cat['robots'], 'seo.index_categories') && $hasPosts;
        $meta = $this->base($this->paged($title, $page), $desc, $url->category($cat['slug'], $page), $index);
        $this->pagination($meta, $page, $pages, fn ($p) => $url->category($cat['slug'], $p));
        $crumbs = $this->crumbs();
        foreach ($ancestors as $a) {
            $crumbs[] = ['name' => $a['name'], 'url' => $url->category($a['slug'])];
        }
        $crumbs[] = ['name' => $cat['name'], 'url' => $url->category($cat['slug'])];
        $meta['breadcrumbs'] = $crumbs;
        $meta['schema'] = array_values(array_filter([$this->organization(), $this->webPage($meta, 'CollectionPage'), $this->breadcrumbList($crumbs, $meta['canonical'])]));
        return $meta;
    }

    public function tag(array $tag, int $page, int $pages, bool $hasPosts): array
    {
        $url = $this->app->url;
        $title = $tag['seo_title'] ?: $this->template((string) $this->s('seo.title_tag'), ['tag_name' => $tag['name'], 'page_number' => $page]);
        $desc = $tag['seo_description'] ?: HtmlSanitizer::summarize((string) $tag['description'], 160);
        $index = $this->termIndexable($tag['robots'], 'seo.index_tags') && $hasPosts;
        $meta = $this->base($this->paged($title, $page), $desc, $url->tag($tag['slug'], $page), $index);
        $this->pagination($meta, $page, $pages, fn ($p) => $url->tag($tag['slug'], $p));
        $crumbs = $this->crumbs();
        $crumbs[] = ['name' => $tag['name'], 'url' => $url->tag($tag['slug'])];
        $meta['breadcrumbs'] = $crumbs;
        $meta['schema'] = array_values(array_filter([$this->organization(), $this->webPage($meta, 'CollectionPage'), $this->breadcrumbList($crumbs, $meta['canonical'])]));
        return $meta;
    }

    public function author(array $author, int $page, int $pages, bool $hasPosts): array
    {
        $url = $this->app->url;
        $title = $author['seo_title'] ?: $this->template((string) $this->s('seo.title_author'), ['author_name' => $author['display_name'], 'page_number' => $page]);
        $desc = $author['seo_description'] ?: HtmlSanitizer::summarize((string) $author['bio'], 160);
        $index = $this->termIndexable($author['robots'], 'seo.index_authors') && $hasPosts;
        $meta = $this->base($this->paged($title, $page), $desc, $url->author($author['slug'], $page), $index, 'profile');
        $this->pagination($meta, $page, $pages, fn ($p) => $url->author($author['slug'], $p));
        $crumbs = $this->crumbs();
        $crumbs[] = ['name' => $author['display_name'], 'url' => $url->author($author['slug'])];
        $meta['breadcrumbs'] = $crumbs;
        $photo = $author['photo_id'] ? $this->app->service(MediaService::class)->find((int) $author['photo_id']) : null;
        if ($photo) {
            $meta['og']['image'] = $this->imageFor($photo, $author['display_name']);
        }
        $page = $this->webPage($meta, 'ProfilePage');
        $page['mainEntity'] = ['@id' => $url->author($author['slug']) . '#person'];
        $meta['schema'] = array_values(array_filter([$this->organization(), $page, $this->person($author, $photo), $this->breadcrumbList($crumbs, $meta['canonical'])]));
        return $meta;
    }

    public function search(string $q, int $page): array
    {
        $title = $q === '' ? 'Search' . ' | ' . $this->siteName() : $this->template((string) $this->s('seo.title_search'), ['query' => $q, 'page_number' => $page]);
        $meta = $this->base($this->paged($title, $page), '', $this->app->url->search($q, $page), $this->app->settings->bool('seo.index_search') && $q !== '');
        return $meta;
    }

    public function notFound(): array
    {
        $meta = $this->base('Page not found | ' . $this->siteName(), '', '', false);
        $meta['canonical'] = '';
        return $meta;
    }

    private function termIndexable(string $robots, string $settingKey): bool
    {
        return match ($robots) {
            'index' => true,
            'noindex' => false,
            default => $this->app->settings->bool($settingKey),
        };
    }

    /** The post's index/noindex directive after applying defaults. */
    public function postRobotsIndex(array $post): bool
    {
        return match ($post['robots_index'] ?? 'default') {
            'index' => true,
            'noindex' => false,
            default => $this->app->settings->bool('seo.robots_posts_index'),
        };
    }

    /** Whether a post belongs in the sitemap: indexable and self-canonical. */
    public function postIndexable(array $post): bool
    {
        return $this->postRobotsIndex($post) && !$this->hasForeignCanonical($post);
    }

    public function postCanonical(array $post): string
    {
        $custom = trim((string) ($post['canonical_url'] ?? ''));
        return $custom !== '' && \BlogFlow\Validation\Validator::isHttpUrl($custom) ? $custom : $this->app->url->post($post['slug']);
    }

    public function hasForeignCanonical(array $post): bool
    {
        return rtrim($this->postCanonical($post), '/') !== rtrim($this->app->url->post($post['slug']), '/');
    }

    public function postTitle(array $post): string
    {
        $custom = trim((string) ($post['seo_title'] ?? ''));
        if ($custom !== '') {
            return $this->template($custom, $this->postVars($post));
        }
        return $this->template((string) $this->s('seo.title_post'), $this->postVars($post));
    }

    public function postDescription(array $post): string
    {
        $custom = trim((string) ($post['meta_description'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $excerpt = trim((string) ($post['excerpt'] ?? ''));
        $vars = $this->postVars($post);
        $vars['post_excerpt'] = $excerpt !== '' ? HtmlSanitizer::summarize($excerpt, 160) : HtmlSanitizer::summarize((string) ($post['content_text'] ?? ''), 155);
        return $this->template((string) $this->s('seo.description_post'), $vars);
    }

    private function postVars(array $post): array
    {
        return [
            'post_title' => $post['title'],
            'category_name' => $post['category_name'] ?? '',
            'author_name' => $post['author_name'] ?? '',
            'post_excerpt' => (string) ($post['excerpt'] ?? ''),
        ];
    }

    /**
     * @param array $post post row with joined category/author fields
     * @param array $tags list of tag rows
     * @param array $ancestors parent categories (root first)
     */
    public function post(array $post, ?array $image, array $tags, array $ancestors, ?array $authorPhoto, bool $preview = false): array
    {
        $url = $this->app->url;
        $canonical = $this->postCanonical($post);
        $index = !$preview && $this->postRobotsIndex($post);
        $meta = $this->base($this->postTitle($post), $this->postDescription($post), $canonical, $index, 'article');
        $follow = match ($post['robots_follow'] ?? 'default') {
            'nofollow' => false,
            default => true,
        };
        $meta['robots'] = $preview ? 'noindex, nofollow' : $this->robots($meta['index'], $follow, (string) ($post['robots_advanced'] ?? ''));

        $ogImageRow = !empty($post['og_image_id']) ? $this->app->service(MediaService::class)->find((int) $post['og_image_id']) : null;
        $meta['og']['title'] = trim((string) ($post['og_title'] ?? '')) ?: $post['title'];
        $meta['og']['description'] = trim((string) ($post['og_description'] ?? '')) ?: $meta['description'];
        $meta['og']['image'] = $this->imageFor($ogImageRow ?? $image, $ogImageRow ? '' : (string) ($post['featured_image_alt'] ?? '')) ?? $meta['og']['image'];
        if (in_array($post['twitter_card'] ?? 'default', ['summary', 'summary_large_image'], true)) {
            $meta['twitter_card'] = $post['twitter_card'];
        }
        $modified = $post['content_modified_at'] ?: $post['published_at'];
        $meta['article'] = [
            'published_time' => bf_iso($post['published_at']),
            'modified_time' => bf_iso($modified),
            'section' => $post['category_name'] ?? '',
            'tags' => array_map(fn ($t) => $t['name'], $tags),
            'author_url' => !empty($post['author_slug']) ? $url->author($post['author_slug']) : '',
        ];

        $crumbs = $this->crumbs();
        foreach ($ancestors as $a) {
            $crumbs[] = ['name' => $a['name'], 'url' => $url->category($a['slug'])];
        }
        if (!empty($post['category_slug'])) {
            $crumbs[] = ['name' => $post['category_name'], 'url' => $url->category($post['category_slug'])];
        }
        $crumbs[] = ['name' => $post['title'], 'url' => $url->post($post['slug'])];
        $meta['breadcrumbs'] = $crumbs;

        $graph = [$this->organization()];
        $webPage = $this->webPage($meta, 'WebPage');
        $webPage['url'] = $url->post($post['slug']);
        $webPage['@id'] = $url->post($post['slug']) . '#webpage';
        $graph[] = $webPage;
        $type = $post['schema_type'] ?? 'default';
        $type = $type === 'default' ? ((string) $this->s('schema.post_type') ?: 'BlogPosting') : $type;
        if (!empty($post['schema_enabled']) && in_array($type, ['BlogPosting', 'Article', 'NewsArticle'], true)) {
            $graph[] = $this->article($post, $type, $meta, $image, $tags, $authorPhoto);
        }
        $graph[] = $this->breadcrumbList($crumbs, $url->post($post['slug']));
        $faq = self::faqItems($post);
        if ($faq && !empty($post['schema_enabled']) && $this->app->settings->bool('schema.faq')) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url->post($post['slug']) . '#faq',
                'mainEntity' => array_map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faq),
            ];
        }
        $meta['schema'] = array_values(array_filter($graph));
        return $meta;
    }

    /** @return array<int, array{q: string, a: string}> */
    public static function faqItems(array $post): array
    {
        $faq = is_string($post['faq'] ?? null) ? json_decode($post['faq'], true) : ($post['faq'] ?? []);
        if (!is_array($faq)) {
            return [];
        }
        $out = [];
        foreach ($faq as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q !== '' && $a !== '') {
                $out[] = ['q' => $q, 'a' => $a];
            }
        }
        return $out;
    }

    // ----- Structured data builders -----

    private function crumbs(): array
    {
        $crumbs = [];
        $main = $this->app->settings->str('general.main_site_url');
        if ($main !== '') {
            $crumbs[] = ['name' => 'Home', 'url' => rtrim($main, '/') . '/'];
        }
        $crumbs[] = ['name' => $this->blogName(), 'url' => $this->app->url->home()];
        return $crumbs;
    }

    public function orgId(): string
    {
        return $this->orgUrl() . '#organization';
    }

    private function orgUrl(): string
    {
        $u = $this->app->settings->str('schema.org_url') ?: $this->app->settings->str('general.main_site_url') ?: $this->app->url->home();
        return rtrim($u, '/') . '/';
    }

    public function organization(): array
    {
        $s = $this->app->settings;
        $type = $s->str('schema.org_type') ?: 'Organization';
        $node = [
            '@type' => $type,
            '@id' => $this->orgId(),
            'name' => $s->str('schema.org_name') ?: $this->siteName(),
            'url' => $this->orgUrl(),
        ];
        $logoId = (int) ($s->get('schema.org_logo') ?: $s->get('theme.logo'));
        $logo = $logoId ? $this->app->service(MediaService::class)->find($logoId) : null;
        if ($logo) {
            $key = $type === 'Person' ? 'image' : 'logo';
            $node[$key] = ['@type' => 'ImageObject', 'url' => $this->app->url->upload($logo['path']), 'width' => (int) $logo['width'], 'height' => (int) $logo['height']];
        }
        $sameAs = array_filter(array_map('trim', explode("\n", $s->str('schema.org_same_as'))), fn ($u) => \BlogFlow\Validation\Validator::isHttpUrl($u));
        foreach (['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'pinterest', 'tiktok'] as $k) {
            if ($v = $s->str('social.' . $k)) {
                $sameAs[] = $v;
            }
        }
        if ($sameAs) {
            $node['sameAs'] = array_values(array_unique($sameAs));
        }
        if ($type !== 'Person' && $type !== 'Organization' && $type !== 'Corporation' && $type !== 'NGO' && $type !== 'EducationalOrganization') {
            // LocalBusiness subtypes: only include contact details the site owner entered.
            if ($addr = $s->str('layout.footer_address')) {
                $node['address'] = ['@type' => 'PostalAddress', 'streetAddress' => trim(preg_replace('/\s*\n\s*/', ', ', $addr) ?? '')];
            }
            if ($phone = $s->str('layout.footer_phone')) {
                $node['telephone'] = $phone;
            }
        }
        if ($email = $s->str('layout.footer_email')) {
            $node['email'] = $email;
        }
        return $node;
    }

    private function website(): array
    {
        $main = $this->app->settings->str('general.main_site_url');
        return [
            '@type' => 'WebSite',
            '@id' => ($main !== '' ? rtrim($main, '/') . '/' : $this->app->url->home()) . '#website',
            'url' => $main !== '' ? rtrim($main, '/') . '/' : $this->app->url->home(),
            'name' => $this->siteName(),
            'publisher' => ['@id' => $this->orgId()],
            'inLanguage' => $this->app->settings->str('general.language') ?: 'en',
        ];
    }

    private function webPage(array $meta, string $type): array
    {
        $node = [
            '@type' => $type,
            '@id' => $meta['canonical'] . '#webpage',
            'url' => $meta['canonical'],
            'name' => $meta['title'],
            'inLanguage' => $this->app->settings->str('general.language') ?: 'en',
        ];
        if ($meta['description'] !== '') {
            $node['description'] = $meta['description'];
        }
        if ($this->app->settings->bool('schema.breadcrumbs') && !empty($meta['breadcrumbs'])) {
            $node['breadcrumb'] = ['@id' => $meta['canonical'] . '#breadcrumb'];
        }
        return $node;
    }

    private function breadcrumbList(array $crumbs, string $pageUrl): ?array
    {
        if (!$this->app->settings->bool('schema.breadcrumbs') || count($crumbs) < 2) {
            return null;
        }
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']];
        }
        return ['@type' => 'BreadcrumbList', '@id' => $pageUrl . '#breadcrumb', 'itemListElement' => $items];
    }

    private function person(array $author, ?array $photo): array
    {
        $url = $this->app->url->author($author['slug']);
        $node = ['@type' => 'Person', '@id' => $url . '#person', 'name' => $author['display_name'] ?? $author['author_name'] ?? '', 'url' => $url];
        if (!empty($author['job_title'])) {
            $node['jobTitle'] = $author['job_title'];
        }
        if (!empty($author['bio'])) {
            $node['description'] = HtmlSanitizer::summarize((string) $author['bio'], 300);
        }
        if ($photo) {
            $node['image'] = $this->app->service(MediaService::class)->url($photo, 'thumb');
        }
        $sameAs = array_values(array_filter(Authors::social($author)));
        if (!empty($author['website_url'])) {
            $sameAs[] = $author['website_url'];
        }
        if ($sameAs) {
            $node['sameAs'] = array_values(array_unique($sameAs));
        }
        return $node;
    }

    private function article(array $post, string $type, array $meta, ?array $image, array $tags, ?array $authorPhoto): array
    {
        $url = $this->app->url->post($post['slug']);
        $node = [
            '@type' => $type,
            '@id' => $url . '#article',
            'headline' => mb_substr($post['title'], 0, 110),
            'description' => $meta['description'],
            'datePublished' => bf_iso($post['published_at']),
            'dateModified' => bf_iso($post['content_modified_at'] ?: $post['published_at']),
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'publisher' => ['@id' => $this->orgId()],
            'inLanguage' => $this->app->settings->str('general.language') ?: 'en',
            'url' => $url,
        ];
        if ($image) {
            $media = $this->app->service(MediaService::class);
            $node['image'] = [
                '@type' => 'ImageObject',
                'url' => $media->url($image),
                'width' => (int) $image['width'],
                'height' => (int) $image['height'],
            ];
            if (!empty($post['featured_image_caption'])) {
                $node['image']['caption'] = $post['featured_image_caption'];
            }
        }
        if (!empty($post['author_slug'])) {
            $node['author'] = $this->person([
                'slug' => $post['author_slug'],
                'display_name' => $post['author_name'],
                'job_title' => $post['author_job_title'] ?? null,
                'bio' => null,
                'social' => $post['author_social'] ?? null,
                'website_url' => $post['author_website'] ?? null,
            ], $authorPhoto);
        } else {
            $node['author'] = ['@id' => $this->orgId()];
        }
        if (!empty($post['category_name'])) {
            $node['articleSection'] = $post['category_name'];
        }
        if ($tags) {
            $node['keywords'] = implode(', ', array_map(fn ($t) => $t['name'], $tags));
        }
        if (!empty($post['word_count'])) {
            $node['wordCount'] = (int) $post['word_count'];
        }
        return $node;
    }

    /** JSON-LD script body (escaped for safe embedding in HTML). */
    public static function jsonLd(array $graph): string
    {
        if (!$graph) {
            return '';
        }
        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => array_values($graph)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** og:locale from a BCP 47 language code when a region is present (en-GB → en_GB). */
    public function ogLocale(): ?string
    {
        $lang = $this->app->settings->str('general.language');
        return preg_match('/^([a-z]{2})[-_]([A-Za-z]{2})$/', $lang, $m) ? strtolower($m[1]) . '_' . strtoupper($m[2]) : null;
    }
}
