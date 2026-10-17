<?php

declare(strict_types=1);

namespace BlogFlow\Core;

/**
 * Builds public (absolute, canonical) and internal URLs from installation settings.
 */
final class Url
{
    private string $base;
    private string $basePath;

    public function __construct(
        string $blogUrl,
        private string $installPath,
        private bool $trailingSlash = false,
        private string $categoryBase = 'category',
        private string $tagBase = 'tag',
        private string $authorBase = 'author',
    ) {
        $this->base = rtrim($blogUrl, '/');
        $this->basePath = rtrim($installPath, '/');
    }

    /** Absolute canonical base, e.g. https://example.com/blogs */
    public function base(): string
    {
        return $this->base;
    }

    /** Path the app is served from on this request, e.g. /blogs */
    public function basePath(): string
    {
        return $this->basePath;
    }

    public function categoryBase(): string
    {
        return $this->categoryBase;
    }

    public function tagBase(): string
    {
        return $this->tagBase;
    }

    public function authorBase(): string
    {
        return $this->authorBase;
    }

    public function trailingSlash(): bool
    {
        return $this->trailingSlash;
    }

    /** Absolute URL for a content path such as "/my-post" with the slash policy applied. */
    public function to(string $path = '/', array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') . ($this->trailingSlash ? '/' : '');
        }
        $url = $this->base . $this->encodePath($path);
        return $query ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function home(int $page = 1): string
    {
        return $page > 1 ? $this->to('/page/' . $page) : $this->base . '/';
    }

    public function post(string $slug): string
    {
        return $this->to('/' . $slug);
    }

    public function category(string $slug, int $page = 1): string
    {
        return $this->to('/' . $this->categoryBase . '/' . $slug . ($page > 1 ? '/page/' . $page : ''));
    }

    public function tag(string $slug, int $page = 1): string
    {
        return $this->to('/' . $this->tagBase . '/' . $slug . ($page > 1 ? '/page/' . $page : ''));
    }

    public function author(string $slug, int $page = 1): string
    {
        return $this->to('/' . $this->authorBase . '/' . $slug . ($page > 1 ? '/page/' . $page : ''));
    }

    public function search(string $q = '', int $page = 1): string
    {
        $query = $q !== '' ? ['q' => $q] : [];
        if ($page > 1) {
            $query['page'] = $page;
        }
        return $this->to('/search', $query);
    }

    /** Non-HTML endpoints never get a trailing slash. */
    public function file(string $name): string
    {
        return $this->base . '/' . ltrim($name, '/');
    }

    public function feed(): string
    {
        return $this->file('feed.xml');
    }

    public function sitemap(): string
    {
        return $this->file('sitemap.xml');
    }

    public function upload(string $relative): string
    {
        return $this->base . '/uploads/' . $this->encodePath(ltrim($relative, '/'));
    }

    /** Root-relative upload path (used inside the admin editor). */
    public function uploadPath(string $relative): string
    {
        return $this->basePath . '/uploads/' . $this->encodePath(ltrim($relative, '/'));
    }

    /** Root-relative asset URL with a cache-busting version. */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = BF_ROOT . '/assets/' . $path;
        $v = is_file($file) ? substr(base_convert((string) filemtime($file), 10, 36), -6) : BF_VERSION;
        return $this->basePath . '/assets/' . $path . '?v=' . $v;
    }

    public function assetAbsolute(string $path): string
    {
        $parts = parse_url($this->base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . $this->asset($path);
    }

    /** Root-relative admin URL. */
    public function admin(string $path = '', array $query = []): string
    {
        $url = $this->basePath . '/admin' . ($path === '' ? '/' : '/' . ltrim($path, '/'));
        return $query ? $url . '?' . http_build_query($query) : $url;
    }

    /** Converts an absolute URL under the blog to a path relative to the blog root, or null. */
    public function relativePath(string $url): ?string
    {
        $url = trim($url);
        if (str_starts_with($url, $this->base)) {
            $rest = substr($url, strlen($this->base));
        } elseif ($this->basePath !== '' && str_starts_with($url, $this->basePath . '/')) {
            $rest = substr($url, strlen($this->basePath));
        } elseif ($this->basePath === '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $rest = $url;
        } else {
            return null;
        }
        if ($rest !== '' && !in_array($rest[0], ['/', '?', '#'], true)) {
            return null;
        }
        $path = (string) parse_url('http://x' . ($rest === '' ? '/' : $rest), PHP_URL_PATH);
        return rawurldecode($path === '' ? '/' : $path);
    }

    public function isExternal(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url($this->base, PHP_URL_HOST));
        return $host !== '' && $host !== $own && $host !== 'www.' . $own && 'www.' . $host !== $own;
    }
}
