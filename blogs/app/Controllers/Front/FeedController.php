<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ContentRenderer;
use BlogFlow\Services\SitemapService;

/**
 * RSS 2.0 feed of the latest published posts.
 */
final class FeedController extends FrontController
{
    public function rss(): Response
    {
        $s = $this->app->settings;
        $url = $this->app->url;
        $x = [SitemapService::class, 'x'];
        $limit = max(5, min(100, $s->int('sitemap.feed_items')));
        $full = $s->bool('sitemap.feed_full_content');
        $rows = $this->db->all(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.content_text, p.content_html, p.published_at, p.content_modified_at, p.featured_image_id,
                    c.name AS category_name, a.display_name AS author_name, m.path AS m_path, m.mime AS m_mime, m.filesize AS m_size
             FROM {{posts}} p LEFT JOIN {{categories}} c ON c.id = p.category_id LEFT JOIN {{authors}} a ON a.id = p.author_id
             LEFT JOIN {{media}} m ON m.id = p.featured_image_id
             WHERE ' . Posts::PUBLIC_WHERE . ' ORDER BY p.published_at DESC LIMIT ?',
            [Posts::now(), $limit]
        );
        $name = $s->str('general.blog_name') ?: 'Blog';
        $desc = $s->str('seo.site_description') ?: $s->str('general.blog_tagline') ?: $name;
        $lastBuild = $rows ? max(array_map(fn ($r) => $r['content_modified_at'] ?: $r['published_at'], $rows)) : gmdate('Y-m-d H:i:s');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n"
            . "<channel>\n"
            . '  <title>' . $x($name) . "</title>\n"
            . '  <link>' . $x($url->home()) . "</link>\n"
            . '  <description>' . $x($desc) . "</description>\n"
            . '  <language>' . $x($s->str('general.language') ?: 'en') . "</language>\n"
            . '  <lastBuildDate>' . gmdate(DATE_RSS, strtotime($lastBuild . ' UTC')) . "</lastBuildDate>\n"
            . '  <atom:link href="' . $x($url->feed()) . '" rel="self" type="application/rss+xml"/>' . "\n"
            . '  <generator>BlogFlow CMS</generator>' . "\n";
        $renderer = $this->app->service(ContentRenderer::class);
        foreach ($rows as $r) {
            $link = $url->post($r['slug']);
            $summary = trim((string) $r['excerpt']) ?: \BlogFlow\Services\HtmlSanitizer::summarize((string) $r['content_text'], 300);
            $xml .= "  <item>\n"
                . '    <title>' . $x($r['title']) . "</title>\n"
                . '    <link>' . $x($link) . "</link>\n"
                . '    <guid isPermaLink="false">' . $x($url->base() . '/?p=' . $r['id']) . "</guid>\n"
                . '    <pubDate>' . gmdate(DATE_RSS, strtotime($r['published_at'] . ' UTC')) . "</pubDate>\n"
                . '    <description>' . $x($summary) . "</description>\n";
            if ($r['author_name']) {
                $xml .= '    <dc:creator>' . $x($r['author_name']) . "</dc:creator>\n";
            }
            if ($r['category_name']) {
                $xml .= '    <category>' . $x($r['category_name']) . "</category>\n";
            }
            if ($r['m_path']) {
                $xml .= '    <enclosure url="' . $x($url->upload($r['m_path'])) . '" length="' . (int) $r['m_size'] . '" type="' . $x($r['m_mime']) . '"/>' . "\n";
            }
            if ($full) {
                $html = $renderer->render((string) $r['content_html'])['html'];
                $xml .= '    <content:encoded><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . "]]></content:encoded>\n";
            }
            $xml .= "  </item>\n";
        }
        $xml .= "</channel>\n</rss>\n";
        return (new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']))->header('X-Robots-Tag', 'noindex');
    }
}
