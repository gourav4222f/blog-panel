<?php

declare(strict_types=1);

namespace BlogFlow\Core;

final class Router
{
    /** @var array<int, array{methods: string[], regex: string, handler: mixed, options: array}> */
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupOptions = [];

    public function get(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $options);
    }

    public function post(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add(['POST'], $pattern, $handler, $options);
    }

    public function any(string $pattern, mixed $handler, array $options = []): void
    {
        $this->add(['GET', 'HEAD', 'POST'], $pattern, $handler, $options);
    }

    public function group(string $prefix, array $options, callable $define): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevOptions = $this->groupOptions;
        $this->groupPrefix .= $prefix;
        $this->groupOptions = array_merge($this->groupOptions, $options);
        $define($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupOptions = $prevOptions;
    }

    public function add(array $methods, string $pattern, mixed $handler, array $options = []): void
    {
        $pattern = $this->groupPrefix . $pattern;
        $pattern = $pattern === '' ? '/' : $pattern;
        $regex = preg_replace_callback('/\{([a-z_]+)(?::([^}]+))?\}/i', function ($m) {
            $re = $m[2] ?? '[^/]+';
            return '(?P<' . $m[1] . '>' . $re . ')';
        }, $pattern);
        $this->routes[] = [
            'methods' => $methods,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'options' => array_merge($this->groupOptions, $options),
        ];
    }

    /**
     * @return array{handler: mixed, params: array, options: array}|null
     * @throws HttpException 405 when the path matches another method.
     */
    public function match(string $method, string $path): ?array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $route['handler'], 'params' => $params, 'options' => $route['options']];
        }
        if ($allowed) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        return null;
    }
}
