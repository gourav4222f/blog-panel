<?php

declare(strict_types=1);

namespace BlogFlow\Core;

use BlogFlow\Controllers\Front\ErrorController;
use BlogFlow\Services\PageCache;
use BlogFlow\Services\RedirectService;
use BlogFlow\Services\Scheduler;
use BlogFlow\Services\Settings;

/**
 * HTTP kernel: boots services, applies URL policies and redirects, dispatches routes,
 * handles caching, security headers and errors.
 */
final class Kernel
{
    public function __construct(private string $basePath)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->renderError($request, $e->status, $e->getMessage());
            foreach ($e->headers as $k => $v) {
                $response->header($k, $v);
            }
        } catch (\Throwable $e) {
            Logger::error($e, ['path' => $request->path, 'method' => $request->method]);
            $response = $this->renderError($request, 500, 'Something went wrong on our side.', $e);
        }
        $this->securityHeaders($request, $response);
        return $response;
    }

    private function dispatch(Request $request): Response
    {
        $config = Config::load();
        if ($config === null || !is_file(BF_ROOT . '/storage/installed.lock')) {
            if ($request->path === '/__bf/rewrite-check') {
                return Response::json(['ok' => true, 'rewrite' => true]);
            }
            return Response::redirect($this->basePath . '/install/', 302);
        }

        if (!empty($config['app']['debug'])) {
            ini_set('display_errors', '0');
        }

        $db = new Database($config['db']);
        $cache = new Cache(BF_ROOT . '/storage/cache');
        $settings = new Settings($db, $cache);
        $this->migrateIfNeeded($db, $cache);

        $blogUrl = (string) ($config['app']['url'] ?? '') ?: $settings->str('url.blog_url');
        if ($blogUrl === '') {
            $blogUrl = ($request->isSecure() ? 'https://' : 'http://') . $request->host() . $this->basePath;
        }
        $url = new Url(
            $blogUrl,
            $this->basePath,
            $settings->str('url.trailing_slash') === 'slash',
            $settings->str('url.category_base') ?: 'category',
            $settings->str('url.tag_base') ?: 'tag',
            $settings->str('url.author_base') ?: 'author',
        );
        $session = new Session(
            'bf_' . substr(hash('sha256', (string) $config['app']['key'] . $this->basePath), 0, 12),
            $this->basePath,
            $request->isSecure(),
            BF_ROOT . '/storage/sessions'
        );
        $auth = new Auth($db, $session, $settings);
        $view = new View(BF_ROOT . '/app/Views');
        $app = new App($config, $db, $cache, $settings, $session, $auth, $view, $url, $request);
        App::set($app);

        date_default_timezone_set('UTC');
        Scheduler::tick($db);

        $isAdmin = $request->path === '/admin' || str_starts_with($request->path, '/admin/');
        $router = Routes::build($url);

        if (!$isAdmin && in_array($request->method, ['GET', 'HEAD'], true)) {
            if ($r = $this->canonicalRedirect($request, $url, $settings)) {
                return $r;
            }
            if ($r = (new RedirectService($app))->match($request)) {
                return $r;
            }
        }

        $matchPath = $request->path === '/' ? '/' : rtrim($request->path, '/');
        $match = $router->match($request->method === 'HEAD' ? 'GET' : $request->method, $matchPath);
        if ($match === null) {
            throw new HttpException(404);
        }
        $options = $match['options'];

        $cacheable = !empty($options['cache']) && $request->method !== 'POST'
            && $settings->bool('general.page_cache') && !$session->hasCookie();
        if ($cacheable && ($hit = PageCache::fetch($request, max(60, $settings->int('general.page_cache_ttl'))))) {
            return $this->conditional($request, $hit);
        }

        if (!empty($options['session']) || $isAdmin) {
            $session->start();
        }
        if ($isAdmin && $request->isPost() && empty($options['nocsrf'])) {
            $token = $request->header('X-CSRF-Token') ?? (string) ($request->post['_token'] ?? '');
            if (!$session->verifyCsrf($token)) {
                throw new HttpException(403, HttpException::reason(419));
            }
        }
        if (!empty($options['auth'])) {
            if (!$auth->check()) {
                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'error' => 'Your session has expired. Please sign in again.'], 401);
                }
                $session->set('intended', $request->path . ($request->rawQuery() !== '' ? '?' . $request->rawQuery() : ''));
                return Response::redirect($url->admin('login'));
            }
            if (!empty($options['perm']) && !$auth->can($options['perm'])) {
                throw new HttpException(403, 'You do not have permission to access this page.');
            }
        }

        [$class, $method] = $match['handler'];
        $controller = new $class($app);
        $response = $controller->$method(...array_values($match['params']));
        if (!$response instanceof Response) {
            $response = Response::html((string) $response);
        }

        if ($cacheable && $response->status() === 200 && $response->getHeader('Set-Cookie') === null) {
            PageCache::store($request, $response);
        }
        if (!$isAdmin && $response->status() === 200 && $request->method !== 'POST' && empty($options['session'])) {
            return $this->conditional($request, $response);
        }
        return $response;
    }

    /** Adds an ETag and answers 304 when the browser copy is current. */
    private function conditional(Request $request, Response $response): Response
    {
        $etag = '"' . substr(sha1($response->body()), 0, 20) . '"';
        $response->header('ETag', $etag);
        if ($response->getHeader('Cache-Control') === null) {
            $response->header('Cache-Control', 'public, max-age=0, must-revalidate');
        }
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return (new Response('', 304))->header('ETag', $etag)->header('Cache-Control', (string) $response->getHeader('Cache-Control'));
        }
        return $response;
    }

    private function canonicalRedirect(Request $request, Url $url, Settings $settings): ?Response
    {
        $path = $request->path;
        $query = $request->rawQuery();
        $target = null;

        $isFile = str_contains(basename($path), '.');
        $isSpecial = str_starts_with($path, '/preview/') || str_starts_with($path, '/__bf/');
        if ($path !== '/' && !$isFile && !$isSpecial) {
            $wantSlash = $url->trailingSlash();
            if ($wantSlash && !str_ends_with($path, '/')) {
                $target = $path . '/';
            } elseif (!$wantSlash && str_ends_with($path, '/')) {
                $target = rtrim($path, '/');
            }
        }

        if ($settings->bool('url.force_canonical_host')) {
            $base = parse_url($url->base());
            $scheme = $request->isSecure() ? 'https' : 'http';
            $hostMismatch = strtolower((string) ($base['host'] ?? '')) !== preg_replace('/:\d+$/', '', $request->host());
            if ($hostMismatch || ($base['scheme'] ?? 'https') !== $scheme) {
                $target ??= $path;
                return Response::redirect($url->base() . $this->encode($target === '/' ? '/' : $target) . ($query !== '' ? '?' . $query : ''), 301);
            }
        }

        if ($target !== null) {
            return Response::redirect($url->basePath() . $this->encode($target) . ($query !== '' ? '?' . $query : ''), 301);
        }
        return null;
    }

    private function encode(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function migrateIfNeeded(Database $db, Cache $cache): void
    {
        $files = glob(BF_ROOT . '/database/migrations/*.sql') ?: [];
        $signature = md5(implode(',', array_map('basename', $files)) . BF_VERSION);
        if ($cache->get('schema_signature') === $signature) {
            return;
        }
        $lock = @fopen(BF_ROOT . '/storage/cache/migrate.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }
        try {
            $migrator = new Migrator($db, BF_ROOT . '/database/migrations');
            $applied = $migrator->migrate();
            if ($applied) {
                Logger::error('Applied migrations: ' . implode(', ', $applied));
            }
            $cache->set('schema_signature', $signature);
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function securityHeaders(Request $request, Response $response): void
    {
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $isAdmin = $request->path === '/admin' || str_starts_with($request->path, '/admin/');
        if ($isAdmin) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
            $response->header('Cache-Control', 'no-store, private');
            $response->header('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "img-src 'self' data: blob: https:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self'",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]));
        }
    }

    private function renderError(Request $request, int $status, string $message, ?\Throwable $e = null): Response
    {
        $isAdmin = $request->path === '/admin' || str_starts_with($request->path, '/admin/');
        if ($request->wantsJson() || ($isAdmin && $request->isPost() && $request->header('X-Requested-With') === 'fetch')) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        $debug = App::booted() && App::get()->debug() && $e !== null;
        try {
            if (App::booted()) {
                $body = (new ErrorController(App::get()))->renderError($status, $message, $isAdmin, $debug ? $e : null);
                return Response::html($body, $status, ['X-Robots-Tag' => 'noindex']);
            }
        } catch (\Throwable $inner) {
            Logger::error($inner);
        }
        $title = htmlspecialchars(HttpException::reason($status), ENT_QUOTES);
        $msg = htmlspecialchars($status >= 500 ? 'The site is temporarily unavailable. Please try again shortly.' : $message, ENT_QUOTES);
        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>' . $title . '</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;background:#FAFAF8;color:#17202A;display:grid;place-items:center;min-height:100vh;margin:0}main{max-width:32rem;padding:2rem}h1{font-size:1.5rem}</style>'
            . '</head><body><main><h1>' . $title . '</h1><p>' . $msg . '</p></main></body></html>';
        return Response::html($body, $status, ['X-Robots-Tag' => 'noindex']);
    }
}
