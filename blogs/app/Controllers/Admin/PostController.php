<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Authors;
use BlogFlow\Models\Categories;
use BlogFlow\Models\Posts;
use BlogFlow\Models\Tags;
use BlogFlow\Services\ContentAnalyzer;
use BlogFlow\Services\MediaService;
use BlogFlow\Services\PostService;
use BlogFlow\Validation\Validator;

final class PostController extends AdminController
{
    private function posts(): Posts
    {
        return $this->app->service(Posts::class);
    }

    private function service(): PostService
    {
        return $this->app->service(PostService::class);
    }

    private function findOr404(int $id): array
    {
        return $this->posts()->find($id) ?? throw new HttpException(404, 'Post not found.');
    }

    private function ownFilter(): array
    {
        if ($this->app->auth->can('posts.edit_all')) {
            return [];
        }
        $u = $this->app->auth->user();
        return ['own_user_id' => (int) $u['id'], 'own_author_id' => (int) ($u['author_profile_id'] ?? 0)];
    }

    public function index(): Response
    {
        $q = $this->request->query;
        $filters = [
            'status' => in_array($q['status'] ?? 'all', ['all', 'draft', 'scheduled', 'published', 'trash', 'featured'], true) ? ($q['status'] ?? 'all') : 'all',
            'q' => is_string($q['q'] ?? null) ? trim($q['q']) : '',
            'category_id' => (int) ($q['category_id'] ?? 0),
            'author_id' => (int) ($q['author_id'] ?? 0),
            'sort' => in_array($q['sort'] ?? '', ['title', 'date', 'updated', 'status'], true) ? $q['sort'] : 'updated',
            'dir' => ($q['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ] + $this->ownFilter();
        $page = $this->pageNumber();
        $per = 20;
        $list = $this->posts()->adminList($filters, $page, $per);
        $own = $this->ownFilter();
        return $this->page('posts/index', [
            'pageTitle' => 'Posts',
            'items' => $list['items'],
            'total' => $list['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($list['total'] / $per)),
            'filters' => $filters,
            'counts' => $this->posts()->counts($own['own_user_id'] ?? null, $own['own_author_id'] ?? null),
            'categories' => $this->app->service(Categories::class)->options(),
            'authors' => $this->app->service(Authors::class)->options(),
        ]);
    }

    public function create(): Response
    {
        $user = $this->app->auth->user();
        $post = [
            'id' => null, 'title' => '', 'slug' => '', 'excerpt' => '', 'content_html' => '', 'status' => 'draft',
            'category_id' => (int) ($this->db->value('SELECT id FROM {{categories}} ORDER BY sort_order, id LIMIT 1') ?? 0),
            'author_id' => $user['author_profile_id'], 'tags' => [], 'related_ids' => [], 'faq' => [], 'show_toc' => 'default',
            'robots_index' => 'default', 'robots_follow' => 'default', 'twitter_card' => 'default', 'schema_type' => 'default',
            'is_featured' => 0, 'published_at' => '', 'created_by' => $user['id'],
        ];
        return $this->editor($post, null);
    }

    public function edit(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->app->auth->canEditPost($post), 'You can only edit your own posts.');
        $input = $this->service()->postToInput($post);
        $autosave = $this->db->one(
            "SELECT id, title, created_at FROM {{post_revisions}} WHERE post_id = ? AND kind = 'autosave' AND user_id = ? AND created_at > ? ORDER BY id DESC LIMIT 1",
            [$post['id'], $this->app->auth->id(), $post['updated_at']]
        );
        return $this->editor($input, $post, $autosave);
    }

    private function editor(array $input, ?array $post, ?array $autosave = null, array $errors = [], int $status = 200): Response
    {
        $media = $this->app->service(MediaService::class);
        $related = [];
        if (!empty($input['related_ids'])) {
            $ids = array_map('intval', (array) $input['related_ids']);
            $rows = $this->db->all('SELECT id, title, status FROM {{posts}} WHERE id IN (' . \BlogFlow\Core\Database::placeholders($ids) . ')', $ids);
            $byId = array_column($rows, null, 'id');
            foreach ($ids as $rid) {
                if (isset($byId[$rid])) {
                    $related[] = $byId[$rid];
                }
            }
        }
        $analysis = $this->app->service(ContentAnalyzer::class)->analyze($input + ['category_name' => '', 'author_name' => '']);
        $editorUser = $post && $post['updated_by'] ? $this->db->value('SELECT name FROM {{users}} WHERE id = ?', [$post['updated_by']]) : null;
        return $this->page('posts/edit', [
            'pageTitle' => $post ? 'Edit: ' . $post['title'] : 'New post',
            'post' => $post,
            'input' => $input,
            'errors' => $errors,
            'autosave' => $autosave,
            'categories' => $this->app->service(Categories::class)->options(),
            'authors' => $this->app->service(Authors::class)->options(),
            'allTags' => $this->db->column('SELECT name FROM {{tags}} ORDER BY name LIMIT 500'),
            'related' => $related,
            'analysis' => $analysis,
            'canPublish' => $this->app->auth->canPublishPost($post ?? ['created_by' => $this->app->auth->id(), 'author_id' => $input['author_id']]),
            'canChooseAuthor' => $this->app->auth->can('posts.edit_all'),
            'revisionCount' => $post ? (int) $this->db->value("SELECT COUNT(*) FROM {{post_revisions}} WHERE post_id = ? AND kind = 'revision'", [$post['id']]) : 0,
            'editorUser' => $editorUser,
            'previewUrl' => $post ? $this->app->url->to('/preview/' . $post['id']) : null,
            'shareUrl' => $post && $post['preview_token'] ? $this->app->url->to('/preview/' . $post['id'], ['token' => $post['preview_token']]) : null,
            'publicUrl' => $post && $post['status'] === 'published' ? $this->app->url->post($post['slug']) : null,
            'mediaCaps' => ['max' => $media->maxUploadBytes()],
            'tz' => $this->app->settings->str('general.timezone') ?: 'UTC',
        ], $status);
    }

    private function input(): array
    {
        $in = $this->request->post;
        $in['tags'] = array_values(array_filter(array_map('trim', explode(',', (string) ($in['tags'] ?? '')))));
        $in['related_ids'] = array_map('intval', (array) ($in['related_ids'] ?? []));
        $in['faq'] = array_values(array_filter((array) ($in['faq'] ?? []), 'is_array'));
        return $in;
    }

    private function action(): string
    {
        $a = (string) ($this->request->post['action'] ?? 'draft');
        return in_array($a, ['draft', 'publish', 'update', 'unpublish', 'save'], true) ? $a : 'draft';
    }

    public function store(): Response
    {
        $in = $this->input();
        $result = $this->service()->save(null, $in, $this->action());
        if ($result['errors']) {
            return $this->editorWithErrors($in, null, $result['errors']);
        }
        $post = $this->posts()->find((int) $result['id']);
        $this->log('post_create', 'post', (int) $result['id'], 'Created “' . $post['title'] . '” (' . $post['status'] . ')');
        $this->flashResult($post, $result['notices'], true);
        return $this->redirect($this->app->url->admin('posts/' . $result['id'] . '/edit'));
    }

    public function update(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->app->auth->canEditPost($post), 'You can only edit your own posts.');
        $in = $this->input();
        $action = $this->action();
        if ($action === 'save') {
            $action = $post['status'] === 'draft' ? 'draft' : 'update';
        }
        $result = $this->service()->save($post, $in, $action);
        if ($result['errors']) {
            return $this->editorWithErrors($in, $post, $result['errors']);
        }
        $fresh = $this->posts()->find((int) $post['id']);
        $this->log('post_update', 'post', (int) $post['id'], 'Updated “' . $fresh['title'] . '” (' . $fresh['status'] . ')');
        $this->flashResult($fresh, $result['notices'], $post['status'] !== 'published');
        return $this->redirect($this->app->url->admin('posts/' . $post['id'] . '/edit'));
    }

    private function editorWithErrors(array $in, ?array $post, array $errors): Response
    {
        $this->app->session->flash('error', $errors['_form'] ?? 'The post was not saved. Please fix the highlighted fields.');
        unset($errors['_form']);
        $in += ['id' => $post['id'] ?? null, 'status' => $post['status'] ?? 'draft', 'created_by' => $post['created_by'] ?? $this->app->auth->id()];
        return $this->editor($in, $post, null, $errors, 422);
    }

    private function flashResult(array $post, array $notices, bool $created): void
    {
        $msg = match ($post['status']) {
            'published' => $created ? 'Post published.' : 'Post updated.',
            'scheduled' => 'Post scheduled.',
            default => 'Draft saved.',
        };
        $this->flash('success', trim($msg . ' ' . implode(' ', $notices)));
    }

    public function autosave(): Response
    {
        $in = $this->input();
        $id = (int) ($in['id'] ?? 0);
        $post = $id ? $this->posts()->find($id) : null;
        if ($id && !$post) {
            return $this->json(['ok' => false, 'error' => 'Post not found.'], 404);
        }
        if ($post && !$this->app->auth->canEditPost($post)) {
            return $this->json(['ok' => false, 'error' => 'Not allowed.'], 403);
        }
        try {
            $r = $this->service()->autosave($post, $in);
        } catch (\RuntimeException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        return $this->json([
            'ok' => true,
            'id' => $r['id'],
            'created' => $r['created'],
            'saved_at' => bf_date($r['saved_at'], 'H:i'),
            'edit_url' => $this->app->url->admin('posts/' . $r['id'] . '/edit'),
            'update_url' => $this->app->url->admin('posts/' . $r['id']),
            'preview_url' => $this->app->url->to('/preview/' . $r['id']),
        ]);
    }

    private function canDelete(array $post): bool
    {
        return $this->app->auth->can('posts.delete_all') || ($this->app->auth->can('posts.delete_own') && $this->app->auth->ownsPost($post) && $post['status'] === 'draft');
    }

    public function trash(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->canDelete($post), 'You cannot delete this post.');
        $this->service()->trash((int) $post['id']);
        $this->log('post_trash', 'post', (int) $post['id'], 'Moved “' . $post['title'] . '” to trash');
        $this->flash('success', 'Post moved to trash. You can restore it from the Trash tab.');
        return $this->redirect($this->app->url->admin('posts'));
    }

    public function restore(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->canDelete($post), 'You cannot restore this post.');
        $this->service()->restore((int) $post['id']);
        $this->log('post_restore', 'post', (int) $post['id'], 'Restored “' . $post['title'] . '” from trash');
        $this->flash('success', 'Post restored.');
        return $this->back($this->app->url->admin('posts', ['status' => 'trash']));
    }

    public function destroy(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->app->auth->can('posts.delete_all'), 'Only editors and administrators can permanently delete posts.');
        if ($post['deleted_at'] === null) {
            $this->flash('error', 'Move the post to trash before deleting it permanently.');
            return $this->back($this->app->url->admin('posts'));
        }
        $this->service()->destroy((int) $post['id']);
        $this->log('post_delete', 'post', (int) $post['id'], 'Permanently deleted “' . $post['title'] . '”');
        $this->flash('success', 'Post permanently deleted.');
        return $this->redirect($this->app->url->admin('posts', ['status' => 'trash']));
    }

    public function duplicate(string $id): Response
    {
        $post = $this->findOr404($this->id($id));
        $this->authorize($this->app->auth->canEditPost($post), 'You cannot duplicate this post.');
        $newId = $this->service()->duplicate($post);
        $this->log('post_duplicate', 'post', $newId, 'Duplicated “' . $post['title'] . '”');
        $this->flash('success', 'Post duplicated as a draft.');
        return $this->redirect($this->app->url->admin('posts/' . $newId . '/edit'));
    }

    public function bulk(): Response
    {
        $action = (string) ($this->request->post['bulk_action'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($this->request->post['ids'] ?? [])))));
        if (!$ids || !in_array($action, ['trash', 'restore', 'destroy', 'publish', 'draft', 'feature', 'unfeature'], true)) {
            $this->flash('error', 'Select posts and an action.');
            return $this->back($this->app->url->admin('posts'));
        }
        $done = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            $post = $this->posts()->find($id);
            if (!$post) {
                continue;
            }
            $allowed = match ($action) {
                'trash', 'restore' => $this->canDelete($post),
                'destroy' => $this->app->auth->can('posts.delete_all') && $post['deleted_at'] !== null,
                'publish', 'draft' => $this->app->auth->canPublishPost($post) && $post['deleted_at'] === null,
                default => $this->app->auth->can('posts.edit_all'),
            };
            if (!$allowed) {
                $skipped++;
                continue;
            }
            switch ($action) {
                case 'trash':
                    $this->service()->trash($id);
                    break;
                case 'restore':
                    $this->service()->restore($id);
                    break;
                case 'destroy':
                    $this->service()->destroy($id);
                    break;
                case 'publish':
                case 'draft':
                    $in = $this->service()->postToInput($post);
                    $r = $this->service()->save($post, $in, $action);
                    if ($r['errors']) {
                        $skipped++;
                        continue 2;
                    }
                    break;
                default:
                    $this->db->update('posts', ['is_featured' => $action === 'feature' ? 1 : 0], 'id = ?', [$id]);
                    \BlogFlow\Services\PageCache::clear();
            }
            $done++;
        }
        $this->log('post_bulk', 'post', null, sprintf('Bulk %s on %d post(s)', $action, $done));
        $this->flash($done ? 'success' : 'error', sprintf('%d post(s) updated.%s', $done, $skipped ? " $skipped skipped (not permitted or invalid)." : ''));
        return $this->back($this->app->url->admin('posts'));
    }

    // ----- Import / export -----

    public function export(): Response
    {
        $format = ($this->request->query['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
        $rows = $this->db->all(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name, a.slug AS author_slug, a.display_name AS author_name,
                    m.path AS featured_image_path
             FROM {{posts}} p LEFT JOIN {{categories}} c ON c.id = p.category_id LEFT JOIN {{authors}} a ON a.id = p.author_id
             LEFT JOIN {{media}} m ON m.id = p.featured_image_id WHERE p.deleted_at IS NULL ORDER BY p.id'
        );
        $tags = $this->posts()->tagsFor(array_column($rows, 'id'));
        $this->log('post_export', 'post', null, sprintf('Exported %d posts (%s)', count($rows), $format));
        $stamp = gmdate('Y-m-d');
        if ($format === 'csv') {
            $fh = fopen('php://temp', 'w+');
            fputcsv($fh, ['id', 'title', 'slug', 'status', 'published_at_utc', 'category', 'author', 'tags', 'excerpt', 'seo_title', 'meta_description', 'focus_keyword', 'word_count', 'url'], ',', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($fh, array_map([self::class, 'csvSafe'], [
                    $r['id'], $r['title'], $r['slug'], $r['status'], $r['published_at'], $r['category_name'], $r['author_name'],
                    implode('; ', array_column($tags[(int) $r['id']] ?? [], 'name')), $r['excerpt'], $r['seo_title'], $r['meta_description'],
                    $r['focus_keyword'], $r['word_count'], $r['status'] === 'published' ? $this->app->url->post($r['slug']) : '',
                ]), ',', '"', '\\');
            }
            rewind($fh);
            $csv = "\xEF\xBB\xBF" . stream_get_contents($fh);
            return new Response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="blogflow-posts-' . $stamp . '.csv"',
            ]);
        }
        $out = ['generator' => 'BlogFlow CMS', 'version' => BF_VERSION, 'type' => 'posts', 'exported_at' => gmdate('c'), 'posts' => []];
        $fields = ['title', 'slug', 'excerpt', 'content_html', 'status', 'is_featured', 'published_at', 'content_modified_at', 'seo_title', 'meta_description',
            'focus_keyword', 'secondary_keywords', 'canonical_url', 'robots_index', 'robots_follow', 'robots_advanced', 'og_title', 'og_description',
            'twitter_card', 'schema_type', 'faq', 'featured_image_alt', 'featured_image_caption', 'show_toc'];
        foreach ($rows as $r) {
            $item = array_intersect_key($r, array_flip($fields));
            $item['faq'] = json_decode((string) $r['faq'], true) ?: [];
            $item['category'] = $r['category_slug'] ? ['slug' => $r['category_slug'], 'name' => $r['category_name']] : null;
            $item['author'] = $r['author_slug'] ? ['slug' => $r['author_slug'], 'name' => $r['author_name']] : null;
            $item['tags'] = array_column($tags[(int) $r['id']] ?? [], 'name');
            $item['featured_image_url'] = $r['featured_image_path'] ? $this->app->url->upload($r['featured_image_path']) : null;
            $out['posts'][] = $item;
        }
        return new Response((string) json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="blogflow-posts-' . $stamp . '.json"',
        ]);
    }

    /** Prevents spreadsheet formula injection in CSV exports. */
    public static function csvSafe(mixed $v): string
    {
        $v = (string) ($v ?? '');
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;
    }

    public function import(): Response
    {
        $file = $this->request->file('file');
        $back = $this->app->url->admin('backup');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 50 * 1024 * 1024) {
            $this->flash('error', 'Upload a BlogFlow posts JSON export (max 50 MB).');
            return $this->redirect($back);
        }
        $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'posts' || !is_array($data['posts'] ?? null)) {
            $this->flash('error', 'This file is not a BlogFlow posts export.');
            return $this->redirect($back);
        }
        $asDrafts = $this->request->bool('as_drafts');
        $cats = $this->app->service(Categories::class);
        $authors = $this->app->service(Authors::class);
        $created = 0;
        $skipped = 0;
        foreach ($data['posts'] as $p) {
            if (!is_array($p) || trim((string) ($p['title'] ?? '')) === '') {
                $skipped++;
                continue;
            }
            $categoryId = null;
            if (!empty($p['category']['slug']) && Validator::isSlug((string) $p['category']['slug'])) {
                $cat = $cats->findBySlug($p['category']['slug']);
                $categoryId = $cat ? (int) $cat['id'] : $cats->create(['name' => mb_substr((string) ($p['category']['name'] ?? $p['category']['slug']), 0, 120), 'slug' => $p['category']['slug']]);
            }
            $authorId = null;
            if (!empty($p['author']['slug'])) {
                $a = $authors->findBySlug((string) $p['author']['slug']);
                $authorId = $a ? (int) $a['id'] : null;
            }
            $slug = Validator::isSlug((string) ($p['slug'] ?? '')) ? (string) $p['slug'] : '';
            if ($slug !== '' && $this->posts()->slugExists($slug)) {
                $slug = '';
            }
            $in = [
                'title' => (string) $p['title'],
                'slug' => $slug,
                'excerpt' => (string) ($p['excerpt'] ?? ''),
                'content_html' => (string) ($p['content_html'] ?? ''),
                'category_id' => $categoryId,
                'author_id' => $authorId ?? $this->app->auth->user()['author_profile_id'],
                'tags' => array_map('strval', (array) ($p['tags'] ?? [])),
                'published_at' => !empty($p['published_at']) ? bf_local_input((string) $p['published_at']) : '',
                'faq' => (array) ($p['faq'] ?? []),
            ];
            foreach (['seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'canonical_url', 'robots_index', 'robots_follow', 'robots_advanced',
                'og_title', 'og_description', 'twitter_card', 'schema_type', 'featured_image_alt', 'featured_image_caption', 'is_featured'] as $k) {
                if (isset($p[$k]) && is_scalar($p[$k])) {
                    $in[$k] = (string) $p[$k];
                }
            }
            $action = !$asDrafts && ($p['status'] ?? '') === 'published' ? 'publish' : 'draft';
            $r = $this->service()->save(null, $in, $action);
            $r['errors'] ? $skipped++ : $created++;
        }
        $this->log('post_import', 'post', null, sprintf('Imported %d posts (%d skipped)', $created, $skipped));
        $this->flash($created ? 'success' : 'error', sprintf('Imported %d post(s); %d skipped. Images are not imported — re-attach featured images in the editor.', $created, $skipped));
        return $this->redirect($this->app->url->admin('posts'));
    }
}
