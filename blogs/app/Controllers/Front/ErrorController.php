<?php

declare(strict_types=1);

namespace BlogFlow\Controllers\Front;

use BlogFlow\Core\HttpException;
use BlogFlow\Models\Posts;

final class ErrorController extends FrontController
{
    public function render404Data(): array
    {
        try {
            return $this->app->service(Posts::class)->publicList([], 1, 3)['items'];
        } catch (\Throwable) {
            return [];
        }
    }

    public function renderError(int $status, string $message = "", bool $isAdmin = false, ?\Throwable $exception = null): string
    {
        $status = (int) $status;
        if ($isAdmin) {
            $user = $this->app->auth->check();
            return $this->app->view->render($user ? 'admin/error' : 'admin/error-guest', [
                'status' => $status,
                'title' => HttpException::reason($status),
                'message' => $status >= 500 ? 'An unexpected error occurred. The details have been written to the error log.' : (string) $message,
                'exception' => $exception,
                'pageTitle' => HttpException::reason($status),
                'user' => $this->app->auth->user(),
                'flash' => [],
            ]);
        }
        $meta = $this->seo()->notFound();
        $meta['title'] = HttpException::reason($status) . ' | ' . $this->seo()->siteName();
        return $this->app->view->render('frontend/error', [
            'status' => $status,
            'title' => $status === 404 ? 'We couldn’t find that page' : HttpException::reason($status),
            'message' => match (true) {
                $status === 404 => 'The article may have moved or no longer exists. Try searching, or start from the latest articles below.',
                $status >= 500 => 'Something went wrong on our side. Please try again in a moment.',
                default => (string) $message,
            },
            'latest' => $status === 404 ? $this->render404Data() : [],
            'exception' => $exception,
            'meta' => $meta,
            'site' => $this->site(),
        ]);
    }
}
