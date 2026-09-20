<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router sederhana dengan dukungan parameter {name}, middleware, dan grup.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,vars:string[],handler:mixed,middleware:array}> */
    private array $routes = [];

    public function get(string $path, $handler, array $mw = []): void    { $this->add('GET', $path, $handler, $mw); }
    public function post(string $path, $handler, array $mw = []): void   { $this->add('POST', $path, $handler, $mw); }
    public function put(string $path, $handler, array $mw = []): void    { $this->add('PUT', $path, $handler, $mw); }
    public function delete(string $path, $handler, array $mw = []): void { $this->add('DELETE', $path, $handler, $mw); }

    public function add(string $method, string $path, $handler, array $mw = []): void
    {
        $vars = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', static function ($m) use (&$vars) {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $path);
        $regex = '#^' . $regex . '$#';
        $this->routes[] = compact('method', 'regex', 'vars', 'handler') + ['middleware' => $mw];
    }

    public function dispatch(Request $req): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $req->path, $matches)) {
                continue;
            }
            if ($route['method'] !== $req->method) {
                $allowed[] = $route['method'];
                continue;
            }
            array_shift($matches);
            foreach ($route['vars'] as $i => $name) {
                $req->params[$name] = $matches[$i] ?? null;
            }
            // Middleware chain
            foreach ($route['middleware'] as $mw) {
                $result = (new $mw())->handle($req);
                if ($result instanceof Response) {
                    return $result; // middleware menghentikan (redirect/403)
                }
            }
            return $this->invoke($route['handler'], $req);
        }

        if ($allowed) {
            return $req->wantsJson()
                ? Response::json(['error' => 'Method Not Allowed'], 405)
                : Response::html('<h1>405 Method Not Allowed</h1>', 405);
        }
        return $req->wantsJson()
            ? Response::json(['error' => 'Not Found'], 404)
            : Response::html(View::render('errors/404', ['title' => '404']), 404);
    }

    private function invoke($handler, Request $req): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $result = $controller->$method($req);
        } elseif (is_callable($handler)) {
            $result = $handler($req);
        } else {
            throw new \RuntimeException('Handler route tidak valid');
        }
        return $result instanceof Response ? $result : Response::html((string) $result);
    }
}
