<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ContentAnalyzer;
use BlogFlow\Services\LinkChecker;
use BlogFlow\Services\SeoAuditService;
use BlogFlow\Services\SitemapService;

final class SeoController extends AdminController
{
    public function overview(): Response
    {
        $s = $this->app->settings;
        $sitemap = $this->app->service(SitemapService::class);
        $entries = $sitemap->entries();
        $now = Posts::now();
        $published = (int) $this->db->value('SELECT COUNT(*) FROM {{posts}} p WHERE ' . Posts::PUBLIC_WHERE, [$now]);
        $counts = [];
        foreach ($this->db->all('SELECT severity, COUNT(*) AS n FROM {{seo_audit_results}} GROUP BY severity') as $r) {
            $counts[$r['severity']] = (int) $r['n'];
        }
        return $this->page('seo/overview', [
            'pageTitle' => 'SEO overview',
            'published' => $published,
            'noindex' => (int) $this->db->value("SELECT COUNT(*) FROM {{posts}} p WHERE robots_index = 'noindex' AND " . Posts::PUBLIC_WHERE, [$now]),
            'noDescription' => (int) $this->db->value("SELECT COUNT(*) FROM {{posts}} p WHERE (p.meta_description IS NULL OR p.meta_description = '') AND (p.excerpt IS NULL OR p.excerpt = '') AND " . Posts::PUBLIC_WHERE, [$now]),
            'noImage' => (int) $this->db->value('SELECT COUNT(*) FROM {{posts}} p WHERE p.featured_image_id IS NULL AND ' . Posts::PUBLIC_WHERE, [$now]),
            'foreignCanonical' => (int) $this->db->value("SELECT COUNT(*) FROM {{posts}} p WHERE p.canonical_url IS NOT NULL AND p.canonical_url <> '' AND " . Posts::PUBLIC_WHERE, [$now]),
            'sitemapCounts' => array_map('count', $entries),
            'sitemapUrl' => $this->app->url->sitemap(),
            'feedUrl' => $this->app->url->feed(),
            'robotsLine' => $sitemap->robotsLine(),
            'mainUrl' => $s->str('general.main_site_url'),
            'counts' => $counts,
            'lastAudit' => $s->get('system.last_audit_at'),
            'verification' => ['Google Search Console' => $s->str('integrations.google_verification') !== '', 'Bing Webmaster Tools' => $s->str('integrations.bing_verification') !== ''],
            'indexing' => [
                'Posts' => $s->bool('seo.robots_posts_index'),
                'Categories' => $s->bool('seo.index_categories'),
                'Tags' => $s->bool('seo.index_tags'),
                'Authors' => $s->bool('seo.index_authors'),
                'Paginated pages' => $s->bool('seo.index_paginated'),
                'Search results' => $s->bool('seo.index_search'),
            ],
        ]);
    }

    public function audit(): Response
    {
        $severity = in_array($this->request->query['severity'] ?? '', ['critical', 'warning', 'suggestion', 'passed'], true) ? $this->request->query['severity'] : '';
        $type = in_array($this->request->query['type'] ?? '', ['site', 'post', 'category', 'tag', 'author', 'redirect', 'media'], true) ? $this->request->query['type'] : '';
        $where = ['1 = 1'];
        $params = [];
        if ($severity !== '') {
            $where[] = 'r.severity = ?';
            $params[] = $severity;
        } else {
            $where[] = "r.severity <> 'passed'";
        }
        if ($type !== '') {
            $where[] = 'r.entity_type = ?';
            $params[] = $type;
        }
        $rows = $this->db->all(
            "SELECT r.*, p.title AS post_title FROM {{seo_audit_results}} r
             LEFT JOIN {{posts}} p ON r.entity_type = 'post' AND p.id = r.entity_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(r.severity, 'critical', 'warning', 'suggestion', 'passed'), r.entity_type, r.entity_id LIMIT 1000",
            $params
        );
        $counts = [];
        foreach ($this->db->all('SELECT severity, COUNT(*) AS n FROM {{seo_audit_results}} GROUP BY severity') as $r) {
            $counts[$r['severity']] = (int) $r['n'];
        }
        $postScores = $this->db->all(
            "SELECT p.id, p.title, SUM(r.severity = 'critical') AS critical, SUM(r.severity = 'warning') AS warning, SUM(r.severity = 'suggestion') AS suggestion, SUM(r.severity = 'passed') AS passed
             FROM {{seo_audit_results}} r INNER JOIN {{posts}} p ON p.id = r.entity_id WHERE r.entity_type = 'post'
             GROUP BY p.id ORDER BY critical DESC, warning DESC, suggestion DESC LIMIT 100"
        );
        return $this->page('seo/audit', [
            'pageTitle' => 'SEO audit',
            'rows' => $rows,
            'counts' => $counts,
            'severity' => $severity,
            'type' => $type,
            'lastAudit' => $this->app->settings->get('system.last_audit_at'),
            'postScores' => $postScores,
        ]);
    }

    public function runAudit(): Response
    {
        $counts = $this->app->service(SeoAuditService::class)->run();
        $this->log('seo_audit', null, null, sprintf('SEO audit: %d critical, %d warnings, %d suggestions', $counts['critical'], $counts['warning'], $counts['suggestion']));
        $this->flash('success', sprintf('Audit complete: %d critical, %d warnings, %d suggestions, %d passed.', $counts['critical'], $counts['warning'], $counts['suggestion'], $counts['passed']));
        return $this->redirect($this->app->url->admin('seo/audit'));
    }

    public function links(): Response
    {
        $checker = $this->app->service(LinkChecker::class);
        $analyzer = $this->app->service(ContentAnalyzer::class);
        $links = $checker->links();
        $checks = [];
        foreach ($this->db->all('SELECT url_hash, status_code, error, checked_at FROM {{link_checks}}') as $r) {
            $checks[$r['url_hash']] = $r;
        }
        $internal = [];
        $external = [];
        foreach ($links as $l) {
            if ($l['internal']) {
                $l['status'] = $analyzer->checkInternalPath((string) $this->app->url->relativePath($l['href']));
                $internal[] = $l;
            } elseif (preg_match('#^https?://#i', $l['href'])) {
                $l['check'] = $checks[sha1($l['href'])] ?? null;
                $external[] = $l;
            }
        }
        usort($internal, fn ($a, $b) => ($a['status'] === 'ok') <=> ($b['status'] === 'ok'));
        usort($external, fn ($a, $b) => self::rank($b['check']) <=> self::rank($a['check']));
        return $this->page('seo/links', [
            'pageTitle' => 'Link audit',
            'internal' => $internal,
            'external' => $external,
            'curl' => function_exists('curl_init'),
        ]);
    }

    private static function rank(?array $check): int
    {
        if (!$check) {
            return 1;
        }
        $code = (int) $check['status_code'];
        return $code >= 400 || $code === 0 ? 3 : ($code >= 300 ? 2 : 0);
    }

    public function checkLinks(): Response
    {
        @set_time_limit(60);
        return $this->json(['ok' => true] + $this->app->service(LinkChecker::class)->checkBatch(5));
    }
}
