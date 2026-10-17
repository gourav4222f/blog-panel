<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;

/**
 * Site-wide, rule-based SEO audit. Results are stored in seo_audit_results.
 */
final class SeoAuditService
{
    private array $rows = [];

    public function __construct(private App $app)
    {
    }

    private function add(string $type, ?int $id, string $key, string $severity, string $message): void
    {
        $this->rows[] = [$type, $id, $key, $severity, mb_substr($message, 0, 500)];
    }

    /** @return array<string, int> counts per severity */
    public function run(): array
    {
        @set_time_limit(300);
        $this->rows = [];
        $db = $this->app->db;
        $s = $this->app->settings;
        $analyzer = $this->app->service(ContentAnalyzer::class);

        // Site configuration
        if (!$s->bool('seo.robots_posts_index')) {
            $this->add('site', null, 'posts_noindex', ContentAnalyzer::CRITICAL, 'All posts default to noindex (Global metadata → Index posts by default is off).');
        }
        if (!str_starts_with($this->app->url->base(), 'https://')) {
            $this->add('site', null, 'https', ContentAnalyzer::WARNING, 'The blog URL does not use HTTPS. Use https:// once an SSL certificate is installed.');
        }
        if ($s->str('seo.site_description') === '' && $s->str('general.intro_text') === '') {
            $this->add('site', null, 'home_description', ContentAnalyzer::WARNING, 'The blog homepage has no meta description.');
        } else {
            $this->add('site', null, 'home_description', ContentAnalyzer::PASSED, 'The blog homepage has a meta description.');
        }
        if (!$s->get('seo.og_default_image')) {
            $this->add('site', null, 'og_default', ContentAnalyzer::SUGGESTION, 'No default social sharing image is set for pages without their own image.');
        }
        if (!$s->get('schema.org_logo') && !$s->get('theme.logo')) {
            $this->add('site', null, 'org_logo', ContentAnalyzer::SUGGESTION, 'No organisation logo is configured for structured data.');
        }
        if ($s->str('general.main_site_url') === '') {
            $this->add('site', null, 'main_url', ContentAnalyzer::SUGGESTION, 'The main website URL is not set, so breadcrumbs and the organisation schema point to the blog.');
        }

        // Posts
        $posts = $db->all("SELECT p.*, c.name AS category_name, a.display_name AS author_name FROM {{posts}} p
            LEFT JOIN {{categories}} c ON c.id = p.category_id LEFT JOIN {{authors}} a ON a.id = p.author_id
            WHERE p.deleted_at IS NULL AND p.status IN ('published', 'scheduled') ORDER BY p.id");
        foreach ($posts as $p) {
            foreach ($analyzer->analyze($p)['checks'] as $c) {
                if (in_array($c['key'], ['keyword_missing', 'h1'], true)) {
                    continue;
                }
                $this->add('post', (int) $p['id'], $c['key'], $c['severity'], $c['message']);
            }
            if (!$p['author_id']) {
                $this->add('post', (int) $p['id'], 'author_missing', ContentAnalyzer::SUGGESTION, 'No author is assigned; article schema falls back to the organisation.');
            }
        }

        // Archives
        foreach (['categories' => 'category', 'tags' => 'tag', 'authors' => 'author'] as $table => $type) {
            $col = $type === 'author' ? 'bio' : 'description';
            foreach ($db->all("SELECT id, slug, robots, $col AS text, seo_description FROM {{" . $table . "}}") as $t) {
                if (trim((string) $t['text']) === '' && trim((string) $t['seo_description']) === '' && $t['robots'] !== 'noindex') {
                    $this->add($type, (int) $t['id'], 'term_description', ContentAnalyzer::SUGGESTION, sprintf('The %s “%s” has no description; its archive page will have a thin intro and no meta description.', $type, $t['slug']));
                }
            }
        }

        // Redirects
        $redirects = $this->app->service(RedirectService::class);
        foreach ($redirects->chains() as $r) {
            $this->add('redirect', (int) $r['id'], 'redirect_chain', ContentAnalyzer::WARNING, sprintf('Redirect chain: %s → %s redirects again. Use “Fix chains” on the Redirects page.', $r['source_path'], $r['destination']));
        }
        foreach ($db->all('SELECT r.id, r.source_path FROM {{redirects}} r WHERE r.is_enabled = 1') as $r) {
            $slug = ltrim($r['source_path'], '/');
            if (!str_contains($slug, '/') && $db->value("SELECT 1 FROM {{posts}} WHERE slug = ? AND deleted_at IS NULL AND status = 'published'", [$slug])) {
                $this->add('redirect', (int) $r['id'], 'redirect_shadow', ContentAnalyzer::WARNING, sprintf('The redirect from %s hides a published post with the same URL.', $r['source_path']));
            }
        }

        // Media
        $limit = max(50, $s->int('images.oversized_kb')) * 1024;
        foreach ($db->all('SELECT id, filename, filesize FROM {{media}} WHERE filesize > ?', [$limit]) as $m) {
            $this->add('media', (int) $m['id'], 'oversized', ContentAnalyzer::WARNING, sprintf('%s is %s. Re-optimise it or lower the maximum width.', $m['filename'], bf_bytes((int) $m['filesize'])));
        }
        $noAlt = (int) $db->value("SELECT COUNT(*) FROM {{media}} WHERE alt_text IS NULL OR alt_text = ''");
        if ($noAlt) {
            $this->add('media', null, 'media_alt', ContentAnalyzer::SUGGESTION, sprintf('%d image(s) in the media library have no default ALT text.', $noAlt));
        }

        $db->transaction(function (Database $db) {
            $db->query('DELETE FROM {{seo_audit_results}}');
            $now = gmdate('Y-m-d H:i:s');
            foreach (array_chunk($this->rows, 200) as $chunk) {
                $sql = 'INSERT INTO {{seo_audit_results}} (entity_type, entity_id, check_key, severity, message, created_at) VALUES '
                    . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?, ?)'));
                $params = [];
                foreach ($chunk as $r) {
                    array_push($params, ...$r);
                    $params[] = $now;
                }
                $db->query($sql, $params);
            }
        });
        $this->app->settings->set('system.last_audit_at', gmdate('Y-m-d H:i:s'));
        $counts = ['critical' => 0, 'warning' => 0, 'suggestion' => 0, 'passed' => 0];
        foreach ($this->rows as $r) {
            $counts[$r[3]]++;
        }
        return $counts;
    }
}
