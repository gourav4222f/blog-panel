<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;
use BlogFlow\Services\SitemapService;

final class SitemapController extends FrontController
{
    public function index(): Response
    {
        return Response::xml($this->app->service(SitemapService::class)->index())
            ->header('X-Robots-Tag', 'noindex');
    }

    public function part(string $type, string $n): Response
    {
        $xml = $this->app->service(SitemapService::class)->part($type, (int) $n);
        if ($xml === null) {
            throw new HttpException(404);
        }
        return Response::xml($xml)->header('X-Robots-Tag', 'noindex');
    }
}
