<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Admin;

use BlogFlow\Core\Controller;
use BlogFlow\Core\HttpException;
use BlogFlow\Core\Response;

abstract class AdminController extends Controller
{
    protected function page(string $template, array $data = [], int $status = 200): Response
    {
        $old = $this->app->session->pullOld();
        $data += [
            'user' => $this->app->auth->user(),
            'flash' => $this->app->session->pullFlash(),
            'old' => $old['input'],
            'errors' => $old['errors'],
        ];
        return $this->view('admin/' . $template, $data, $status);
    }

    protected function id(string $id): int
    {
        $n = (int) $id;
        if ($n <= 0) {
            throw new HttpException(404);
        }
        return $n;
    }

    protected function isFetch(): bool
    {
        return $this->request->header('X-Requested-With') === 'fetch';
    }

    /** Current page number from ?page= */
    protected function pageNumber(): int
    {
        return max(1, (int) ($this->request->query['page'] ?? 1));
    }
}
