<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Response;
use BlogFlow\Core\Routes;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ContentAnalyzer;
use BlogFlow\Services\HtmlSanitizer;
use BlogFlow\Validation\Validator;

/**
 * JSON endpoints used by the editor (analysis, internal link picker, slug checks).
 */
final class ApiController extends AdminController
{
    public function seoAnalyze(): Response
    {
        $in = $this->request->post;
        $post = [
            'id' => (int) ($in['id'] ?? 0),
            'title' => (string) ($in['title'] ?? ''),
            'slug' => (string) ($in['slug'] ?? '') ?: Validator::slugify((string) ($in['title'] ?? '')),
            'excerpt' => (string) ($in['excerpt'] ?? ''),
            'content_html' => HtmlSanitizer::clean((string) ($in['content_html'] ?? '')),
            'seo_title' => (string) ($in['seo_title'] ?? ''),
            'meta_description' => (string) ($in['meta_description'] ?? ''),
            'focus_keyword' => (string) ($in['focus_keyword'] ?? ''),
            'featured_image_id' => (int) ($in['featured_image_id'] ?? 0) ?: null,
            'featured_image_alt' => (string) ($in['featured_image_alt'] ?? ''),
            'robots_index' => (string) ($in['robots_index'] ?? 'default'),
            'canonical_url' => (string) ($in['canonical_url'] ?? ''),
            'category_name' => $this->db->value('SELECT name FROM {{categories}} WHERE id = ?', [(int) ($in['category_id'] ?? 0)]) ?? '',
            'author_name' => $this->db->value('SELECT display_name FROM {{authors}} WHERE id = ?', [(int) ($in['author_id'] ?? 0)]) ?? '',
        ];
        return $this->json(['ok' => true] + $this->app->service(ContentAnalyzer::class)->analyze($post));
    }

    /** Post search for the internal link picker and related-post selector. */
    public function posts(): Response
    {
        $q = trim((string) ($this->request->query['q'] ?? ''));
        $exclude = (int) ($this->request->query['exclude'] ?? 0);
        $params = [$exclude];
        $where = 'deleted_at IS NULL AND id <> ?';
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR slug LIKE ?)';
            $like = '%' . Posts::likeEscape($q) . '%';
            array_push($params, $like, $like);
        }
        $rows = $this->db->all("SELECT id, title, slug, status FROM {{posts}} WHERE $where ORDER BY (status = 'published') DESC, updated_at DESC LIMIT 15", $params);
        $out = array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'status' => $r['status'],
            'url' => $this->app->url->post($r['slug']),
        ], $rows);
        if ($q !== '' && !empty($this->request->query['categories'])) {
            foreach ($this->db->all('SELECT name, slug FROM {{categories}} WHERE name LIKE ? LIMIT 5', ['%' . Posts::likeEscape($q) . '%']) as $c) {
                $out[] = ['id' => 0, 'title' => 'Category: ' . $c['name'], 'status' => 'category', 'url' => $this->app->url->category($c['slug'])];
            }
        }
        return $this->json(['ok' => true, 'items' => $out]);
    }

    public function slug(): Response
    {
        $slug = strtolower(trim((string) ($this->request->query['slug'] ?? '')));
        $id = (int) ($this->request->query['id'] ?? 0);
        if ($slug === '') {
            return $this->json(['ok' => true, 'status' => 'empty']);
        }
        if (!Validator::isSlug($slug)) {
            return $this->json(['ok' => true, 'status' => 'invalid', 'message' => 'Use lowercase letters, numbers and hyphens.', 'suggestion' => Validator::slugify($slug)]);
        }
        if (in_array($slug, Routes::reservedSlugs($this->app->url), true)) {
            return $this->json(['ok' => true, 'status' => 'reserved', 'message' => 'Reserved by the system.']);
        }
        $other = $this->db->value('SELECT title FROM {{posts}} WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($other) {
            return $this->json(['ok' => true, 'status' => 'taken', 'message' => 'Used by “' . $other . '”.']);
        }
        return $this->json(['ok' => true, 'status' => 'available', 'message' => 'Available']);
    }
}
