<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Tiny pattern-matching router with named routes and middleware.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: mixed, name: ?string, middleware: list<string>, params: list<string>}> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $names = [];

    private string $prefix = '';

    /** @var list<string> */
    private array $groupMiddleware = [];

    /**
     * @param string|array{0: string, 1: string} $handler Class name or [class, method] pair
     * @param array{middleware?: list<string>|string, name?: string} $options
     */
    public function add(string $method, string $pattern, string|array|callable $handler, array $options = []): self
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $action] = explode('@', $handler, 2);
            $handler = [$class, $action];
        }

        $fullPattern = rtrim($this->prefix, '/') . '/' . ltrim($pattern, '/');
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return isset($m[2]) ? '(?:/(?P<' . $m[1] . '>[^/]+))?' : '(?P<' . $m[1] . '>[^/]+)';
            },
            $fullPattern
        ) ?? $fullPattern;

        $middleware = $options['middleware'] ?? [];
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $name = $options['name'] ?? null;

        $index = count($this->routes);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $fullPattern,
            'regex' => '#^' . $regex . '/?$#',
            'handler' => $handler,
            'name' => $name,
            'middleware' => array_values(array_merge($this->groupMiddleware, $middleware)),
            'params' => $params,
        ];

        if ($name !== null) {
            $this->names[$name] = $index;
        }

        return $this;
    }

    /** @param array{middleware?: list<string>|string, prefix?: string} $options */
    public function group(array $options, callable $callback): self
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = $previousPrefix . '/' . trim((string) ($options['prefix'] ?? ''), '/');
        $middleware = $options['middleware'] ?? [];
        $this->groupMiddleware = array_merge(
            $previousMiddleware,
            is_array($middleware) ? $middleware : [$middleware]
        );

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    public function get(string $pattern, string|array|callable $handler, array $options = []): self
    {
        return $this->add('GET', $pattern, $handler, $options);
    }

    public function post(string $pattern, string|array|callable $handler, array $options = []): self
    {
        return $this->add('POST', $pattern, $handler, $options);
    }

    public function put(string $pattern, string|array|callable $handler, array $options = []): self
    {
        return $this->add('PUT', $pattern, $handler, $options);
    }

    public function delete(string $pattern, string|array|callable $handler, array $options = []): self
    {
        return $this->add('DELETE', $pattern, $handler, $options);
    }

    public function any(string $pattern, string|array|callable $handler, array $options = []): self
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->add($method, $pattern, $handler, $options);
        }

        return $this;
    }

    public function route(string $name, array $parameters = []): string
    {
        if (!isset($this->names[$name])) {
            return url('/');
        }

        $pattern = $this->routes[$this->names[$name]]['pattern'];
        $path = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}/',
            static fn (array $m): string => isset($parameters[$m[1]]) ? rawurlencode((string) $parameters[$m[1]]) : '',
            $pattern
        ) ?? $pattern;

        return url(preg_replace('#/+#', '/', $path) ?? $path);
    }

    public function dispatch(Request $request, \Cms\App $app): Response
    {
        $method = $request->method();
        $path = $request->path();
        $pathMatches = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatches[] = $route;
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }

            $parameters = [];
            foreach ($route['params'] as $param) {
                if (isset($matches[$param]) && $matches[$param] !== '') {
                    $parameters[$param] = $matches[$param];
                }
            }

            foreach ($route['middleware'] as $middleware) {
                $response = $this->runMiddleware($middleware, $request, $app);
                if ($response instanceof Response) {
                    return $response;
                }
            }

            return $this->invoke($route['handler'], $parameters, $app);
        }

        if ($pathMatches !== []) {
            return new Response($app->view()->render('errors/405', [
                'meta' => ['title' => 'Method Not Allowed'],
            ]), 405);
        }

        return new Response($app->view()->render('errors/404', [
            'meta' => ['title' => 'Page not found'],
        ]), 404);
    }

    private function runMiddleware(string $middleware, Request $request, \Cms\App $app): ?Response
    {
        [$name, $argument] = array_pad(explode(':', $middleware, 2), 2, null);

        return match ($name) {
            'auth' => $app->auth()->check() ? null : Response::redirect('/admin/login', 302),
            'guest' => $app->auth()->check() ? Response::redirect('/admin', 302) : null,
            'admin' => $app->auth()->hasRole('admin')
                ? null
                : new Response($app->view()->render('errors/403', ['meta' => ['title' => 'Forbidden']]), 403),
            'role' => in_array($app->auth()->role(), explode(',', (string) $argument), true)
                ? null
                : new Response($app->view()->render('errors/403', ['meta' => ['title' => 'Forbidden']]), 403),
            'csrf' => Csrf::verify($request->string('_token')) ? null : new Response(
                $app->view()->render('errors/419', ['meta' => ['title' => 'Session expired']]),
                419
            ),
            default => null,
        };
    }

    /** @param array{0: string, 1: string}|callable $handler */
    private function invoke(array|callable $handler, array $parameters, \Cms\App $app): Response
    {
        if (is_callable($handler)) {
            $result = $handler($app, ...array_values($parameters));
        } else {
            [$class, $action] = $handler;
            $controller = new $class($app);
            $result = $controller->{$action}(...array_values($parameters));
        }

        return $result instanceof Response ? $result : Response::make((string) $result);
    }
}
