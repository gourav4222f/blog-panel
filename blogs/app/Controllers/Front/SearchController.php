<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Models\Posts;

final class SearchController extends FrontController
{
    public function index(): Response
    {
        if (!$this->app->settings->bool('general.show_search')) {
            throw new HttpException(404);
        }
        $q = $this->request->query['q'] ?? '';
        $q = is_string($q) ? trim(mb_substr(preg_replace('/\s+/u', ' ', $q) ?? '', 0, 100)) : '';
        $page = max(1, (int) ($this->request->query['page'] ?? 1));
        $items = [];
        $total = 0;
        $pages = 1;
        if ($q !== '') {
            $list = $this->app->service(Posts::class)->publicList(['q' => $q], $page, $this->perPage());
            $items = $list['items'];
            $total = $list['total'];
            $pages = max(1, (int) ceil($total / $this->perPage()));
            if ($page > $pages && $total > 0) {
                throw new HttpException(404);
            }
        }
        return $this->render('frontend/search', [
            'q' => $q,
            'posts' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'pageUrl' => fn (int $p) => $this->app->url->search($q, $p),
        ], $this->seo()->search($q, $page));
    }
}
