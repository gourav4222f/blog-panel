<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Authors;
use BlogFlow\Models\Categories;
use BlogFlow\Models\Posts;
use BlogFlow\Models\Tags;

final class ArchiveController extends FrontController
{
    public function category(string $slug, string $page = '1'): Response
    {
        if ($r = $this->canonicalFirstPage($page, fn () => $this->app->url->category($slug))) {
            return $r;
        }
        $page = (int) $page;
        $cats = $this->app->service(Categories::class);
        $cat = $cats->findBySlug($slug) ?? throw new HttpException(404);
        $list = $this->app->service(Posts::class)->publicList(['category_ids' => $cats->descendantIds((int) $cat['id'])], $page, $this->perPage());
        $pages = $this->pages($list['total'], $this->perPage(), $page);
        $image = $cat['image_id'] ? $this->media()->find((int) $cat['image_id']) : null;
        $children = array_values(array_filter($cats->all(), fn ($c) => (int) $c['parent_id'] === (int) $cat['id']));
        return $this->render('frontend/archive', [
            'kind' => 'category',
            'eyebrow' => 'Category',
            'heading' => $cat['name'],
            'description' => (string) $cat['description'],
            'image' => $image,
            'children' => $children,
            'posts' => $list['items'],
            'total' => $list['total'],
            'page' => $page,
            'pages' => $pages,
            'pageUrl' => fn (int $p) => $this->app->url->category($cat['slug'], $p),
        ], $this->seo()->category($cat, $cats->ancestors($cat), $page, $pages, $list['total'] > 0));
    }

    public function tag(string $slug, string $page = '1'): Response
    {
        if ($r = $this->canonicalFirstPage($page, fn () => $this->app->url->tag($slug))) {
            return $r;
        }
        $page = (int) $page;
        $tag = $this->app->service(Tags::class)->findBySlug($slug) ?? throw new HttpException(404);
        $list = $this->app->service(Posts::class)->publicList(['tag_id' => (int) $tag['id']], $page, $this->perPage());
        $pages = $this->pages($list['total'], $this->perPage(), $page);
        return $this->render('frontend/archive', [
            'kind' => 'tag',
            'eyebrow' => 'Tag',
            'heading' => $tag['name'],
            'description' => (string) $tag['description'],
            'posts' => $list['items'],
            'total' => $list['total'],
            'page' => $page,
            'pages' => $pages,
            'pageUrl' => fn (int $p) => $this->app->url->tag($tag['slug'], $p),
        ], $this->seo()->tag($tag, $page, $pages, $list['total'] > 0));
    }

    public function author(string $slug, string $page = '1'): Response
    {
        if ($r = $this->canonicalFirstPage($page, fn () => $this->app->url->author($slug))) {
            return $r;
        }
        $page = (int) $page;
        $author = $this->app->service(Authors::class)->findBySlug($slug) ?? throw new HttpException(404);
        $list = $this->app->service(Posts::class)->publicList(['author_id' => (int) $author['id']], $page, $this->perPage());
        $pages = $this->pages($list['total'], $this->perPage(), $page);
        $photo = $author['photo_id'] ? $this->media()->find((int) $author['photo_id']) : null;
        return $this->render('frontend/archive', [
            'kind' => 'author',
            'eyebrow' => $author['job_title'] ?: 'Author',
            'heading' => $author['display_name'],
            'description' => (string) $author['bio'],
            'author' => $author,
            'photo' => $photo,
            'posts' => $list['items'],
            'total' => $list['total'],
            'page' => $page,
            'pages' => $pages,
            'pageUrl' => fn (int $p) => $this->app->url->author($author['slug'], $p),
        ], $this->seo()->author($author, $page, $pages, $list['total'] > 0));
    }
}
