<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Models\Posts;

/**
 * XML sitemap generation. Only canonical, indexable, published URLs are listed.
 */
final class SitemapService
{
    public function __construct(private App $app)
    {
    }

    private function seo(): SeoService
    {
        return $this->app->service(SeoService::class);
    }

    /** @return array<string, array<int, array{loc: string, lastmod: ?string, images: array}>> */
    public function entries(): array
    {
        $s = $this->app->settings;
        $db = $this->app->db;
        $url = $this->app->url;
        $now = Posts::now();
        $out = ['home' => [], 'posts' => [], 'categories' => [], 'tags' => [], 'authors' => []];

        $latest = $db->value('SELECT MAX(COALESCE(p.content_modified_at, p.published_at)) FROM {{posts}} p WHERE ' . Posts::PUBLIC_WHERE, [$now]);
        $out['home'][] = ['loc' => $url->home(), 'lastmod' => $latest, 'images' => []];

        if ($s->bool('sitemap.include_posts')) {
            $rows = $db->all(
                'SELECT p.id, p.slug, p.title, p.published_at, p.content_modified_at, p.robots_index, p.canonical_url, p.featured_image_alt,
                        m.path AS m_path, m.alt_text AS m_alt
                 FROM {{posts}} p LEFT JOIN {{media}} m ON m.id = p.featured_image_id
                 WHERE ' . Posts::PUBLIC_WHERE . ' ORDER BY p.published_at DESC',
                [$now]
            );
            foreach ($rows as $r) {
                if (!$this->seo()->postIndexable($r)) {
                    continue;
                }
                $images = [];
                if ($s->bool('sitemap.include_images') && $r['m_path']) {
                    $images[] = $url->upload($r['m_path']);
                }
                $out['posts'][] = ['loc' => $url->post($r['slug']), 'lastmod' => $r['content_modified_at'] ?: $r['published_at'], 'images' => $images];
            }
        }

        $terms = [
            'categories' => ['categories', 'category_id', 'seo.index_categories', 'sitemap.include_categories', fn ($slug) => $url->category($slug)],
            'authors' => ['authors', 'author_id', 'seo.index_authors', 'sitemap.include_authors', fn ($slug) => $url->author($slug)],
        ];
        foreach ($terms as $key => [$table, $col, $indexKey, $includeKey, $make]) {
            if (!$s->bool($includeKey)) {
                continue;
            }
            $rows = $db->all(
                'SELECT t.slug, t.robots, MAX(COALESCE(p.content_modified_at, p.published_at)) AS lastmod FROM {{' . $table . '}} t
                 INNER JOIN {{posts}} p ON p.' . $col . ' = t.id AND ' . Posts::PUBLIC_WHERE . ' GROUP BY t.id ORDER BY t.slug',
                [$now]
            );
            foreach ($rows as $r) {
                if ($r['robots'] === 'noindex' || ($r['robots'] !== 'index' && !$s->bool($indexKey))) {
                    continue;
                }
                $out[$key][] = ['loc' => $make($r['slug']), 'lastmod' => $r['lastmod'], 'images' => []];
            }
        }
        if ($s->bool('sitemap.include_tags')) {
            $rows = $db->all(
                'SELECT t.slug, t.robots, MAX(COALESCE(p.content_modified_at, p.published_at)) AS lastmod FROM {{tags}} t
                 INNER JOIN {{post_tags}} pt ON pt.tag_id = t.id INNER JOIN {{posts}} p ON p.id = pt.post_id AND ' . Posts::PUBLIC_WHERE . '
                 GROUP BY t.id ORDER BY t.slug',
                [$now]
            );
            foreach ($rows as $r) {
                if ($r['robots'] === 'noindex' || ($r['robots'] !== 'index' && !$s->bool('seo.index_tags'))) {
                    continue;
                }
                $out['tags'][] = ['loc' => $url->tag($r['slug']), 'lastmod' => $r['lastmod'], 'images' => []];
            }
        }
        return $out;
    }

    public function maxUrls(): int
    {
        return max(100, min(50000, $this->app->settings->int('sitemap.max_urls')));
    }

    /** Either a single urlset or a sitemap index when the URL count exceeds the limit. */
    public function index(): string
    {
        $entries = $this->entries();
        $total = array_sum(array_map('count', $entries));
        if ($total <= $this->maxUrls()) {
            return $this->urlset(array_merge(...array_values($entries)));
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $type => $list) {
            foreach (array_chunk($list, $this->maxUrls()) as $i => $chunk) {
                $lastmod = max(array_map(fn ($e) => (string) $e['lastmod'], $chunk)) ?: null;
                $xml .= '  <sitemap><loc>' . self::x($this->app->url->file('sitemap-' . $type . '-' . ($i + 1) . '.xml')) . '</loc>'
                    . ($lastmod ? '<lastmod>' . self::x(bf_iso($lastmod)) . '</lastmod>' : '') . "</sitemap>\n";
            }
        }
        return $xml . '</sitemapindex>' . "\n";
    }

    public function part(string $type, int $n): ?string
    {
        $entries = $this->entries();
        $chunks = array_chunk($entries[$type] ?? [], $this->maxUrls());
        return isset($chunks[$n - 1]) ? $this->urlset($chunks[$n - 1]) : null;
    }

    private function urlset(array $entries): string
    {
        $hasImages = (bool) array_filter($entries, fn ($e) => $e['images']);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ($hasImages ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . ">\n";
        foreach ($entries as $e) {
            $xml .= '  <url><loc>' . self::x($e['loc']) . '</loc>';
            if ($e['lastmod']) {
                $xml .= '<lastmod>' . self::x(bf_iso($e['lastmod'])) . '</lastmod>';
            }
            foreach ($e['images'] as $img) {
                $xml .= '<image:image><image:loc>' . self::x($img) . '</image:loc></image:image>';
            }
            $xml .= "</url>\n";
        }
        return $xml . "</urlset>\n";
    }

    public static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Line to add to the website's root robots.txt. */
    public function robotsLine(): string
    {
        return 'Sitemap: ' . $this->app->url->sitemap();
    }
}
