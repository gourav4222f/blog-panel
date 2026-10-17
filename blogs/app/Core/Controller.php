<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use BlogFlow\Services\ActivityLog;

abstract class Controller
{
    protected Database $db;
    protected Request $request;

    public function __construct(protected App $app)
    {
        $this->db = $app->db;
        $this->request = $app->request;
    }

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data), $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function back(string $fallback): Response
    {
        $ref = (string) $this->request->header('Referer');
        $path = $ref !== '' ? (string) parse_url($ref, PHP_URL_PATH) : '';
        $query = $ref !== '' ? (string) parse_url($ref, PHP_URL_QUERY) : '';
        // Only same-site admin paths are allowed as a "back" target.
        if ($path !== '' && str_starts_with($path, $this->app->url->admin())) {
            return $this->redirect($path . ($query !== '' ? '?' . $query : ''));
        }
        return $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session->flash($type, $message);
    }

    protected function withErrors(string $to, array $errors, ?array $input = null): Response
    {
        $this->app->session->flashInput($input ?? $this->request->post, $errors);
        $this->flash('error', count($errors) === 1 ? (string) reset($errors) : 'Please correct the highlighted fields.');
        return $this->redirect($to);
    }

    protected function log(string $action, ?string $type = null, ?int $id = null, ?string $summary = null): void
    {
        ActivityLog::record($this->app, $action, $type, $id, $summary);
    }

    protected function authorize(bool $allowed, string $message = 'You do not have permission to do that.'): void
    {
        if (!$allowed) {
            throw new HttpException(403, $message);
        }
    }
}
