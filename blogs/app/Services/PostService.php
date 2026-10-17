<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use BlogFlow\Core\Database;
use BlogFlow\Core\Routes;
use BlogFlow\Models\Posts;
use BlogFlow\Models\Tags;
use BlogFlow\Validation\Validator;

/**
 * Creating, updating, duplicating, trashing and restoring posts, with revisions,
 * tag/related syncing, media usage tracking and automatic slug redirects.
 */
final class PostService
{
    public const MAX_REVISIONS = 40;

    private Database $db;

    public function __construct(private App $app)
    {
        $this->db = $app->db;
    }

    private function tz(): string
    {
        return $this->app->settings->str('general.timezone') ?: 'UTC';
    }

    /**
     * @param ?array $existing current post row, or null to create
     * @param string $action draft|publish|update|unpublish
     * @return array{id: ?int, errors: array, notices: array}
     */
    public function save(?array $existing, array $in, string $action): array
    {
        $auth = $this->app->auth;
        $errors = [];
        $notices = [];
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')) ?? '');
        if ($title === '') {
            $errors['title'] = 'Add a title.';
        } elseif (mb_strlen($title) > 255) {
            $errors['title'] = 'The title must be at most 255 characters.';
        }

        // Slug
        $slugInput = trim((string) ($in['slug'] ?? ''));
        $autoSlug = $slugInput === '';
        $slug = $autoSlug ? Validator::slugify($title) : strtolower($slugInput);
        if ($slug === '' && $title !== '') {
            $slug = 'post-' . date('YmdHis');
        }
        $reserved = Routes::reservedSlugs($this->app->url);
        $posts = $this->app->service(Posts::class);
        if ($slug !== '') {
            if (!Validator::isSlug($slug)) {
                $errors['slug'] = 'The slug may only contain lowercase letters, numbers and single hyphens.';
            } elseif (in_array($slug, $reserved, true) || preg_match('/^(page|sitemap)(-|$)/', $slug)) {
                if ($autoSlug) {
                    $slug .= '-post';
                } else {
                    $errors['slug'] = '“' . $slug . '” is reserved by the system. Choose another slug.';
                }
            }
            if (!isset($errors['slug']) && $posts->slugExists($slug, $existing['id'] ?? null)) {
                if ($autoSlug) {
                    $base = $slug;
                    for ($i = 2; $posts->slugExists($slug, $existing['id'] ?? null); $i++) {
                        $slug = $base . '-' . $i;
                    }
                } else {
                    $other = $this->db->value('SELECT title FROM {{posts}} WHERE slug = ?', [$slug]);
                    $errors['slug'] = sprintf('This slug is already used by “%s”.', $other);
                }
            }
        }

        // Content
        $rawHtml = (string) ($in['content_html'] ?? '');
        if (strlen($rawHtml) > 4_000_000) {
            $errors['content_html'] = 'The content is too large.';
        }
        $html = HtmlSanitizer::clean($rawHtml);
        $text = HtmlSanitizer::text($html);
        $words = HtmlSanitizer::wordCount($text);
        $json = (string) ($in['content_json'] ?? '');
        if ($json !== '' && (strlen($json) > 8_000_000 || !is_array(json_decode($json, true)))) {
            $json = '';
        }

        // Relations
        $categoryId = (int) ($in['category_id'] ?? 0) ?: null;
        if ($categoryId && !$this->db->value('SELECT 1 FROM {{categories}} WHERE id = ?', [$categoryId])) {
            $errors['category_id'] = 'Choose a valid category.';
        }
        $authorId = (int) ($in['author_id'] ?? 0) ?: null;
        $user = $auth->user();
        if (!$auth->can('posts.edit_all')) {
            // Authors always publish under their own profile.
            $authorId = $user['author_profile_id'] ? (int) $user['author_profile_id'] : ($existing['author_id'] ?? null);
        } elseif ($authorId && !$this->db->value('SELECT 1 FROM {{authors}} WHERE id = ?', [$authorId])) {
            $errors['author_id'] = 'Choose a valid author.';
        }
        $mediaIds = [];
        foreach (['featured_image_id', 'og_image_id'] as $k) {
            $v = (int) ($in[$k] ?? 0) ?: null;
            if ($v && !$this->db->value('SELECT 1 FROM {{media}} WHERE id = ?', [$v])) {
                $v = null;
            }
            $mediaIds[$k] = $v;
        }

        // SEO fields
        $canonical = trim((string) ($in['canonical_url'] ?? ''));
        if ($canonical !== '' && !Validator::isHttpUrl($canonical)) {
            $errors['canonical_url'] = 'The canonical URL must be an absolute http(s) URL.';
        }
        $robotsAdvanced = implode(', ', SeoService::parseAdvancedRobots((string) ($in['robots_advanced'] ?? '')));
        $limits = ['seo_title' => 255, 'meta_description' => 500, 'focus_keyword' => 190, 'secondary_keywords' => 500, 'og_title' => 255, 'og_description' => 500, 'excerpt' => 1000, 'featured_image_alt' => 500, 'featured_image_caption' => 500];
        foreach ($limits as $k => $max) {
            if (mb_strlen(trim((string) ($in[$k] ?? ''))) > $max) {
                $errors[$k] = sprintf('Must be at most %d characters.', $max);
            }
        }

        // FAQ
        $faq = [];
        foreach ((array) ($in['faq'] ?? []) as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q !== '' && $a !== '') {
                $faq[] = ['q' => mb_substr($q, 0, 300), 'a' => mb_substr($a, 0, 3000)];
            }
        }

        // Status & dates
        $current = $existing['status'] ?? 'draft';
        $publishedAt = $existing['published_at'] ?? null;
        $dateInput = trim((string) ($in['published_at'] ?? ''));
        if ($dateInput !== '') {
            $d = Validator::parseDate($dateInput, $this->tz());
            if (!$d) {
                $errors['published_at'] = 'Enter a valid publication date.';
            } else {
                $publishedAt = $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        $draftLike = ['id' => $existing['id'] ?? 0, 'created_by' => $existing['created_by'] ?? $auth->id(), 'author_id' => $authorId];
        $canPublish = $auth->canPublishPost($draftLike);
        $status = $current;
        switch ($action) {
            case 'publish':
            case 'update':
                if (!$canPublish) {
                    if ($current !== 'draft') {
                        $errors['_form'] = 'You do not have permission to change a published post.';
                    }
                    $status = 'draft';
                    $notices[] = 'Saved as a draft. An editor needs to publish it.';
                    break;
                }
                if ($action === 'update' && $current === 'draft') {
                    $status = 'draft';
                    break;
                }
                $publishedAt ??= gmdate('Y-m-d H:i:s');
                $status = strtotime($publishedAt . ' UTC') > time() + 30 ? 'scheduled' : 'published';
                break;
            case 'unpublish':
                if (!$canPublish) {
                    $errors['_form'] = 'You do not have permission to unpublish this post.';
                }
                $status = 'draft';
                break;
            default:
                if ($current !== 'draft' && !$canPublish) {
                    $errors['_form'] = 'You do not have permission to change a published post.';
                }
                $status = $current === 'draft' ? 'draft' : $current;
                if ($action === 'draft') {
                    $status = 'draft';
                }
        }
        if ($errors) {
            return ['id' => $existing['id'] ?? null, 'errors' => $errors, 'notices' => []];
        }

        $now = gmdate('Y-m-d H:i:s');
        $contentChanged = !$existing || $existing['title'] !== $title || HtmlSanitizer::text((string) $existing['content_html']) !== $text;
        $contentModifiedAt = $existing['content_modified_at'] ?? null;
        if ($status === 'published' && $current !== 'published') {
            $contentModifiedAt = $publishedAt;
        } elseif ($contentChanged && empty($in['minor_edit']) && in_array($current, ['published', 'scheduled'], true)) {
            $contentModifiedAt = $now;
        }
        $showToc = match ((string) ($in['show_toc'] ?? 'default')) {
            '1' => 1,
            '0' => 0,
            default => null,
        };

        $row = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => trim((string) ($in['excerpt'] ?? '')) ?: null,
            'content_json' => $json !== '' ? $json : null,
            'content_html' => $html,
            'content_text' => $text,
            'status' => $status,
            'is_featured' => $auth->can('posts.edit_all') ? (!empty($in['is_featured']) ? 1 : 0) : (int) ($existing['is_featured'] ?? 0),
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'featured_image_id' => $mediaIds['featured_image_id'],
            'featured_image_alt' => trim((string) ($in['featured_image_alt'] ?? '')) ?: null,
            'featured_image_caption' => trim((string) ($in['featured_image_caption'] ?? '')) ?: null,
            'show_toc' => $showToc,
            'published_at' => $publishedAt,
            'content_modified_at' => $contentModifiedAt,
            'seo_title' => trim((string) ($in['seo_title'] ?? '')) ?: null,
            'meta_description' => trim(preg_replace('/\s+/u', ' ', (string) ($in['meta_description'] ?? '')) ?? '') ?: null,
            'focus_keyword' => trim((string) ($in['focus_keyword'] ?? '')) ?: null,
            'secondary_keywords' => trim((string) ($in['secondary_keywords'] ?? '')) ?: null,
            'canonical_url' => $canonical ?: null,
            'robots_index' => in_array($in['robots_index'] ?? '', ['index', 'noindex'], true) ? $in['robots_index'] : 'default',
            'robots_follow' => in_array($in['robots_follow'] ?? '', ['follow', 'nofollow'], true) ? $in['robots_follow'] : 'default',
            'robots_advanced' => $robotsAdvanced ?: null,
            'og_title' => trim((string) ($in['og_title'] ?? '')) ?: null,
            'og_description' => trim((string) ($in['og_description'] ?? '')) ?: null,
            'og_image_id' => $mediaIds['og_image_id'],
            'twitter_card' => in_array($in['twitter_card'] ?? '', ['summary', 'summary_large_image'], true) ? $in['twitter_card'] : 'default',
            'schema_type' => in_array($in['schema_type'] ?? '', ['BlogPosting', 'Article', 'NewsArticle', 'none'], true) ? $in['schema_type'] : 'default',
            'schema_enabled' => ($in['schema_type'] ?? '') === 'none' ? 0 : 1,
            'faq' => $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : null,
            'word_count' => $words,
            'reading_time' => HtmlSanitizer::readingTime($words),
            'updated_by' => $auth->id(),
            'updated_at' => $now,
        ];

        $tagNames = is_array($in['tags'] ?? null) ? $in['tags'] : explode(',', (string) ($in['tags'] ?? ''));
        $relatedIds = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($in['related_ids'] ?? []))))), 0, 6);

        $id = $this->db->transaction(function (Database $db) use ($existing, $row, $tagNames, $relatedIds, $html, $now, $in) {
            if ($existing) {
                $id = (int) $existing['id'];
                $db->update('posts', $row, 'id = ?', [$id]);
            } else {
                $row['created_by'] = $this->app->auth->id();
                $row['created_at'] = $now;
                $row['preview_token'] = bin2hex(random_bytes(16));
                $id = $db->insert('posts', $row);
            }
            $this->syncTags($id, $tagNames);
            $this->syncRelated($id, $relatedIds);
            $this->syncMedia($id, $html, [$row['featured_image_id'], $row['og_image_id']]);
            $this->revision($id, $row, 'revision', trim((string) ($in['revision_note'] ?? '')) ?: null);
            $db->delete('post_revisions', "post_id = ? AND kind = 'autosave' AND user_id = ?", [$id, $this->app->auth->id()]);
            return $id;
        });

        // Redirect the old URL when a live post's slug changes.
        $redirects = $this->app->service(RedirectService::class);
        if ($existing && $existing['slug'] !== $slug && in_array($current, ['published', 'scheduled'], true)) {
            if (!empty($in['create_redirect'])) {
                $redirects->slugChanged('/' . $existing['slug'], '/' . $slug, 'Slug changed: ' . mb_substr($title, 0, 200));
                $notices[] = sprintf('301 redirect created from /%s to /%s.', $existing['slug'], $slug);
            }
        }
        $redirects->releasePath('/' . $slug);

        Scheduler::refresh($this->db);
        PageCache::clear();
        if ($status === 'scheduled') {
            $notices[] = 'Scheduled for ' . bf_date($publishedAt, 'M j, Y H:i') . ' (' . $this->tz() . ').';
        }
        return ['id' => $id, 'errors' => [], 'notices' => $notices];
    }

    private function syncTags(int $postId, array $names): void
    {
        $ids = $this->app->service(Tags::class)->resolveNames(array_slice($names, 0, 30));
        $this->db->delete('post_tags', 'post_id = ?', [$postId]);
        foreach ($ids as $tagId) {
            $this->db->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    private function syncRelated(int $postId, array $ids): void
    {
        $this->db->delete('post_related', 'post_id = ?', [$postId]);
        $order = 0;
        foreach ($ids as $rid) {
            if ($rid !== $postId && $this->db->value('SELECT 1 FROM {{posts}} WHERE id = ?', [$rid])) {
                $this->db->insert('post_related', ['post_id' => $postId, 'related_id' => $rid, 'sort_order' => $order++]);
            }
        }
    }

    /** Records which media a post uses (content images, featured and social images). */
    public function syncMedia(int $postId, string $html, array $extra = []): void
    {
        preg_match_all('/data-media-id="(\d+)"/', $html, $m);
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge($m[1], $extra)))));
        $this->db->delete('post_media', 'post_id = ?', [$postId]);
        if (!$ids) {
            return;
        }
        $existing = $this->db->column('SELECT id FROM {{media}} WHERE id IN (' . Database::placeholders($ids) . ')', $ids);
        foreach ($existing as $mid) {
            $this->db->insert('post_media', ['post_id' => $postId, 'media_id' => (int) $mid]);
        }
    }

    private function revision(int $postId, array $row, string $kind, ?string $note): void
    {
        $meta = array_intersect_key($row, array_flip([
            'slug', 'status', 'category_id', 'author_id', 'featured_image_id', 'featured_image_alt', 'featured_image_caption',
            'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'canonical_url', 'robots_index', 'robots_follow',
            'robots_advanced', 'og_title', 'og_description', 'og_image_id', 'twitter_card', 'schema_type', 'faq', 'published_at', 'is_featured', 'show_toc',
        ]));
        $this->db->insert('post_revisions', [
            'post_id' => $postId,
            'user_id' => $this->app->auth->id(),
            'kind' => $kind,
            'title' => $row['title'],
            'excerpt' => $row['excerpt'],
            'content_json' => $row['content_json'],
            'content_html' => $row['content_html'],
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'note' => $note,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $keep = $this->db->column("SELECT id FROM {{post_revisions}} WHERE post_id = ? AND kind = 'revision' ORDER BY id DESC LIMIT " . self::MAX_REVISIONS, [$postId]);
        if (count($keep) >= self::MAX_REVISIONS) {
            $this->db->query("DELETE FROM {{post_revisions}} WHERE post_id = ? AND kind = 'revision' AND id < ?", [$postId, (int) min($keep)]);
        }
    }

    /**
     * Stores an autosave for the current user without touching the live post.
     * Creates a draft first when the post has never been saved.
     *
     * @return array{id: int, saved_at: string}
     */
    public function autosave(?array $existing, array $in): array
    {
        if (!$existing) {
            $in['title'] = trim((string) ($in['title'] ?? '')) ?: 'Untitled draft';
            $result = $this->save(null, $in, 'draft');
            if ($result['errors']) {
                throw new \RuntimeException((string) reset($result['errors']));
            }
            return ['id' => (int) $result['id'], 'saved_at' => gmdate('Y-m-d H:i:s'), 'created' => true];
        }
        $html = HtmlSanitizer::clean((string) ($in['content_html'] ?? ''));
        $meta = array_intersect_key($in, array_flip(['seo_title', 'meta_description', 'featured_image_id', 'featured_image_alt', 'featured_image_caption', 'slug', 'focus_keyword']));
        $faq = array_values(array_filter((array) ($in['faq'] ?? []), fn ($f) => trim((string) ($f['q'] ?? '')) !== ''));
        if ($faq) {
            $meta['faq'] = json_encode($faq, JSON_UNESCAPED_UNICODE);
        }
        $this->db->transaction(function (Database $db) use ($existing, $in, $html, $meta) {
            $db->delete('post_revisions', "post_id = ? AND kind = 'autosave' AND user_id = ?", [$existing['id'], $this->app->auth->id()]);
            $db->insert('post_revisions', [
                'post_id' => $existing['id'],
                'user_id' => $this->app->auth->id(),
                'kind' => 'autosave',
                'title' => mb_substr(trim((string) ($in['title'] ?? '')) ?: $existing['title'], 0, 255),
                'excerpt' => trim((string) ($in['excerpt'] ?? '')) ?: null,
                'content_json' => null,
                'content_html' => $html,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        });
        return ['id' => (int) $existing['id'], 'saved_at' => gmdate('Y-m-d H:i:s'), 'created' => false];
    }

    public function trash(int $id): void
    {
        $this->db->update('posts', ['deleted_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$id]);
        Scheduler::refresh($this->db);
        PageCache::clear();
    }

    public function restore(int $id): void
    {
        $post = $this->db->one('SELECT slug FROM {{posts}} WHERE id = ?', [$id]);
        $this->db->update('posts', ['deleted_at' => null], 'id = ?', [$id]);
        if ($post) {
            $this->app->service(RedirectService::class)->releasePath('/' . $post['slug']);
        }
        Scheduler::refresh($this->db);
        PageCache::clear();
    }

    public function destroy(int $id): void
    {
        $this->db->delete('posts', 'id = ? AND deleted_at IS NOT NULL', [$id]);
        PageCache::clear();
    }

    public function duplicate(array $post): int
    {
        $posts = $this->app->service(Posts::class);
        $base = Validator::slugify('copy of ' . $post['title']);
        $slug = $base;
        for ($i = 2; $posts->slugExists($slug); $i++) {
            $slug = $base . '-' . $i;
        }
        $now = gmdate('Y-m-d H:i:s');
        return $this->db->transaction(function (Database $db) use ($post, $slug, $now) {
            $copy = $post;
            unset($copy['id']);
            $copy['title'] = mb_substr('Copy of ' . $post['title'], 0, 255);
            $copy['slug'] = $slug;
            $copy['status'] = 'draft';
            $copy['is_featured'] = 0;
            $copy['published_at'] = null;
            $copy['content_modified_at'] = null;
            $copy['deleted_at'] = null;
            $copy['preview_token'] = bin2hex(random_bytes(16));
            $copy['created_by'] = $this->app->auth->id();
            $copy['updated_by'] = $this->app->auth->id();
            $copy['created_at'] = $now;
            $copy['updated_at'] = $now;
            $newId = $db->insert('posts', $copy);
            $db->query('INSERT INTO {{post_tags}} (post_id, tag_id) SELECT ?, tag_id FROM {{post_tags}} WHERE post_id = ?', [$newId, $post['id']]);
            $db->query('INSERT INTO {{post_related}} (post_id, related_id, sort_order) SELECT ?, related_id, sort_order FROM {{post_related}} WHERE post_id = ? AND related_id <> ?', [$newId, $post['id'], $newId]);
            $db->query('INSERT INTO {{post_media}} (post_id, media_id) SELECT ?, media_id FROM {{post_media}} WHERE post_id = ?', [$newId, $post['id']]);
            return $newId;
        });
    }

    /** Restores a revision as the current content (the restore itself becomes a new revision). */
    public function restoreRevision(array $post, array $revision): void
    {
        $meta = json_decode((string) $revision['meta'], true) ?: [];
        $in = array_merge($this->postToInput($post), $meta, [
            'title' => $revision['title'],
            'excerpt' => $revision['excerpt'],
            'content_html' => $revision['content_html'],
            'content_json' => $revision['content_json'],
            'slug' => $post['slug'],
            'published_at' => bf_local_input($post['published_at']),
            'faq' => json_decode((string) ($meta['faq'] ?? $post['faq'] ?? '[]'), true) ?: [],
            'revision_note' => 'Restored revision from ' . bf_date($revision['created_at'], 'M j, Y H:i'),
        ]);
        $action = $post['status'] === 'draft' ? 'draft' : 'update';
        $result = $this->save($post, $in, $action);
        if ($result['errors']) {
            throw new \RuntimeException((string) reset($result['errors']));
        }
    }

    /** Converts a stored post into the input shape accepted by save(). */
    public function postToInput(array $post): array
    {
        $tags = $this->db->column('SELECT t.name FROM {{post_tags}} pt INNER JOIN {{tags}} t ON t.id = pt.tag_id WHERE pt.post_id = ?', [$post['id']]);
        $related = $this->db->column('SELECT related_id FROM {{post_related}} WHERE post_id = ? ORDER BY sort_order', [$post['id']]);
        $in = $post;
        $in['tags'] = $tags;
        $in['related_ids'] = $related;
        $in['show_toc'] = $post['show_toc'] === null ? 'default' : (string) (int) $post['show_toc'];
        $in['faq'] = json_decode((string) ($post['faq'] ?? '[]'), true) ?: [];
        $in['published_at'] = bf_local_input($post['published_at']);
        return $in;
    }
}
