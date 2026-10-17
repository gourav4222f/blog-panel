<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;

final class HomeController extends FrontController
{
    public function index(string $page = '1'): Response
    {
        if ($r = $this->canonicalFirstPage($page, fn () => $this->app->url->home())) {
            return $r;
        }
        $page = (int) $page;
        $posts = $this->app->service(Posts::class);
        $featured = $this->app->settings->bool('general.show_featured') ? $posts->featured() : null;
        $list = $posts->publicList(['exclude' => $featured ? [(int) $featured['id']] : []], $page, $this->perPage());
        $pages = $this->pages($list['total'], $this->perPage(), $page);
        return $this->render('frontend/home', [
            'featured' => $page === 1 ? $featured : null,
            'posts' => $list['items'],
            'page' => $page,
            'pages' => $pages,
            'total' => $list['total'],
            'pageUrl' => fn (int $p) => $this->app->url->home($p),
        ], $this->seo()->home($page, $pages));
    }

    /** Used by the installer to confirm URL rewriting works. */
    public function rewriteCheck(): Response
    {
        return Response::json(['ok' => true, 'rewrite' => true]);
    }
}
