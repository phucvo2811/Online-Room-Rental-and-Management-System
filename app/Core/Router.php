<?php
namespace App\Core;

class Router
{
    private array  $routes     = [];
    private array  $middleware = [];
    private string $prefix     = '';
    

    public function get(string $path, string $controller, string $method, array $mw = []): void
    {
        $this->add('GET', $path, $controller, $method, $mw);
    }

    public function post(string $path, string $controller, string $method, array $mw = []): void
    {
        $this->add('POST', $path, $controller, $method, $mw);
    }

    private function add(string $httpMethod, string $path, string $controller, string $action, array $mw): void
    {
        $this->routes[] = [
            'method'     => $httpMethod,
            'path'       => $this->prefix . $path,
            'controller' => $controller,
            'action'     => $action,
            'middleware' => array_merge($this->middleware, $mw),
        ];
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $prevPrefix = $this->prefix;
        $prevMw     = $this->middleware;
        $this->prefix     = $prevPrefix . $prefix;
        $this->middleware = array_merge($prevMw, $middleware);
        $callback($this);
        $this->prefix     = $prevPrefix;
        $this->middleware = $prevMw;
    }

    public function dispatch(): void
    {
        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri        = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath   = parse_url(APP_URL, PHP_URL_PATH) ?? '';
        $uri        = '/' . trim(substr($uri, strlen($basePath)), '/');
        if ($uri === '') $uri = '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $httpMethod) continue;
            $pattern = '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $route['path']) . '$#';
            if (!preg_match($pattern, $uri, $matches)) continue;
            array_shift($matches);

            foreach ($route['middleware'] as $mwClass) {
                if (!(new $mwClass())->handle()) return;
            }

            $class = 'App\\Controllers\\' . $route['controller'];
            (new $class())->{$route['action']}(...$matches);
            return;
        }

        http_response_code(404);
        echo \App\Core\Container::get('twig')->render('errors/404.twig');
    }
    
}