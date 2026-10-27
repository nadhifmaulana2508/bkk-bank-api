<?php
declare(strict_types=1);

final class ApiRouter
{
    /**
     * @var array<int, array{methods: array<int, string>, path: string, handler: callable}>
     */
    private array $routes = [];

    /**
     * @param array<int, string> $methods
     */
    public function add(array $methods, string $path, callable $handler): self
    {
        $normalizedMethods = array_map('strtoupper', $methods);
        $this->routes[] = [
            'methods' => $normalizedMethods,
            'path' => $path,
            'handler' => $handler,
        ];

        return $this;
    }

    public function get(string $path, callable $handler): self
    {
        return $this->add(['GET'], $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add(['POST'], $path, $handler);
    }

    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route['path'] !== $path || !in_array($method, $route['methods'], true)) {
                continue;
            }

            ($route['handler'])();
            return true;
        }

        return false;
    }
}
