<?php
declare(strict_types=1);

final class ApiRouter
{
    /**
     * @var array<int, array{
     *     methods: array<int, string>,
     *     path: string,
     *     handler: callable,
     *     middleware: array<int, callable>
     * }>
     */
    private array $routes = [];

    /**
     * @var array<int, callable>
     */
    private array $globalMiddleware = [];

    public function addGlobalMiddleware(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, callable> $middleware
     */
    public function add(
        array $methods,
        string $path,
        callable $handler,
        array $middleware = []
    ): self {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'path' => '/' . trim($path, '/'),
            'handler' => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function get(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function post(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function put(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function delete(string $path, callable $handler, array $middleware = []): self
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function dispatch(string $method, string $path): bool
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $routeParams = $this->matchPath($route['path'], $path);
            if ($routeParams === null) {
                continue;
            }

            ApiRequest::setRouteParams($routeParams);

            $pipeline = $route['handler'];
            $middlewareStack = array_merge($this->globalMiddleware, $route['middleware']);
            foreach (array_reverse($middlewareStack) as $middleware) {
                $next = $pipeline;
                $pipeline = static function () use ($middleware, $next): void {
                    $middleware($next);
                };
            }

            $pipeline();
            return true;
        }

        return false;
    }

    /**
     * Match an exact route or a route containing {parameter} segments.
     *
     * @return array<string, string>|null
     */
    private function matchPath(string $routePath, string $requestPath): ?array
    {
        if (!str_contains($routePath, '{')) {
            return $routePath === $requestPath ? [] : null;
        }

        $pattern = preg_quote($routePath, '#');
        $pattern = preg_replace(
            '/\\\\\{([A-Za-z][A-Za-z0-9_]*)\\\\\}/',
            '(?P<$1>[^/]+)',
            $pattern
        );
        if (!is_string($pattern) || preg_match('#^' . $pattern . '$#', $requestPath, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $name => $value) {
            if (is_string($name) && !is_int($name) && is_string($value)) {
                $params[$name] = $value;
            }
        }

        return $params;
    }
}
