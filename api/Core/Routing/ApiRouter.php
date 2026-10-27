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
            if ($route['path'] !== $path || !in_array($method, $route['methods'], true)) {
                continue;
            }

            $pipeline = $route['handler'];
            foreach (array_reverse($route['middleware']) as $middleware) {
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
}
