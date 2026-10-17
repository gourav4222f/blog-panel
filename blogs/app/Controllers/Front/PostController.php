<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Categories;
use BlogFlow\Models\Posts;
use BlogFlow\Services\ContentRenderer;
use BlogFlow\Services\SeoService;

final class PostController extends FrontController
{
    public function show(string $slug): Response
    {
        $post = $this->app->service(Posts::class)->findPublicBySlug($slug);
        if (!$post) {
            throw new HttpException(404);
        }
        return $this->article($post, false);
    }

    /**
     * Private preview of any post (drafts, scheduled, unsaved autosaves). Requires an
     * editor session or the post's preview token. Never indexed or cached.
     */
    public function preview(string $id): Response
    {
        $post = $this->app->service(Posts::class)->findWithRelations((int) $id);
        if (!$post) {
            throw new HttpException(404);
        }
        $token = $this->request->query['token'] ?? '';
        $tokenOk = $post['preview_token'] && is_string($token) && hash_equals((string) $post['preview_token'], $token);
        if (!$tokenOk && !$this->app->auth->canEditPost($post)) {
            throw new HttpException(404);
        }
        // Show the user's newer autosave when previewing from the editor.
        if ($this->app->auth->check() && isset($this->request->query['autosave'])) {
            $auto = $this->db->one(
                "SELECT * FROM {{post_revisions}} WHERE post_id = ? AND kind = 'autosave' AND user_id = ? ORDER BY id DESC LIMIT 1",
                [$post['id'], $this->app->auth->id()]
            );
            if ($auto) {
                $post['title'] = $auto['title'];
                $post['excerpt'] = $auto['excerpt'];
                $post['content_html'] = $auto['content_html'];
                $meta = json_decode((string) $auto['meta'], true) ?: [];
                foreach (['seo_title', 'meta_description', 'faq', 'featured_image_id', 'featured_image_alt', 'featured_image_caption'] as $k) {
                    if (array_key_exists($k, $meta)) {
                        $post[$k] = $meta[$k];
                    }
                }
            }
        }
        $post['published_at'] = $post['published_at'] ?: gmdate('Y-m-d H:i:s');
        $response = $this->article($post, true);
        $response->header('X-Robots-Tag', 'noindex, nofollow');
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }

    private function article(array $post, bool $preview): Response
    {
        $posts = $this->app->service(Posts::class);
        $media = $this->media();
        $image = $post['featured_image_id'] ? $media->find((int) $post['featured_image_id']) : null;
        $authorPhoto = !empty($post['author_photo_id']) ? $media->find((int) $post['author_photo_id']) : null;
        $tags = $posts->tagsFor([(int) $post['id']])[(int) $post['id']] ?? [];
        $ancestors = [];
        if (!empty($post['category_id'])) {
            $cats = $this->app->service(Categories::class);
            $cat = $cats->find((int) $post['category_id']);
            $ancestors = $cat ? $cats->ancestors($cat) : [];
        }
        $rendered = $this->app->service(ContentRenderer::class)->render((string) $post['content_html']);
        $s = $this->app->settings;
        $showToc = $post['show_toc'] === null ? $s->bool('general.show_toc') : (bool) $post['show_toc'];
        $related = $s->bool('general.show_related') && !$preview ? $posts->related($post, max(1, $s->int('general.related_count'))) : [];
        $adjacent = $s->bool('general.show_prev_next') && !$preview ? $posts->adjacent($post) : ['prev' => null, 'next' => null];

        return $this->render('frontend/post', [
            'post' => $post,
            'image' => $image,
            'authorPhoto' => $authorPhoto,
            'tags' => $tags,
            'content' => $rendered['html'],
            'toc' => $showToc && count($rendered['toc']) >= 3 ? $rendered['toc'] : [],
            'faq' => SeoService::faqItems($post),
            'related' => $related,
            'adjacent' => $adjacent,
            'preview' => $preview,
        ], $this->seo()->post($post, $image, $tags, $ancestors, $authorPhoto, $preview));
    }
}
