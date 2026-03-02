<?php

namespace App\Core;

/**
 * Routeur MVC léger
 * Supporte GET, POST, PUT, DELETE, PATCH
 * Supporte les paramètres de route {param}
 */
class Router
{
    /** @var array[] Routes enregistrées */
    private array $routes = [];

    /** @var callable Gestionnaire 404 */
    private $notFoundHandler;

    /**
     * Enregistre une route GET
     */
    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Enregistre une route POST
     */
    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Enregistre une route PUT
     */
    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Enregistre une route DELETE
     */
    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Enregistre une route PATCH
     */
    public function patch(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    /**
     * Enregistre une route pour plusieurs méthodes
     */
    public function match(array $methods, string $path, $handler, array $middlewares = []): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler, $middlewares);
        }
    }

    /**
     * Définit le gestionnaire 404
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Dispatch la requête
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $uri    = $request->uri();

        // Support du _method pour PUT/DELETE via formulaires HTML
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) {
                $method = $override;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match_route($route['pattern'], $uri);
            if ($params !== false) {
                // Exécuter les middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $mw = new $middleware();
                    $mw->handle($request);
                }

                // Injecter les paramètres dans la requête
                $request->setRouteParams($params);

                // Appeler le handler
                $this->callHandler($route['handler'], $request, $params);
                return;
            }
        }

        // 404
        if ($this->notFoundHandler) {
            call_user_func($this->notFoundHandler, $request);
        } else {
            http_response_code(404);
            echo '<h1>404 — Page non trouvée</h1>';
        }
    }

    // ─── Privé ────────────────────────────────────────────────────────────────

    private function addRoute(string $method, string $path, $handler, array $middlewares): void
    {
        $pattern = $this->pathToPattern($path);
        $this->routes[] = [
            'method'      => $method,
            'path'        => $path,
            'pattern'     => $pattern,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Convertit /campaigns/{id}/edit en regex
     */
    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Tente de faire correspondre l'URI au pattern
     * Retourne les paramètres capturés ou false
     */
    private function match_route(string $pattern, string $uri): array|false
    {
        if (preg_match($pattern, $uri, $matches)) {
            // Ne garder que les captures nommées
            return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }
        return false;
    }

    /**
     * Appelle le handler (callable ou [Controller::class, 'method'])
     */
    private function callHandler($handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $request, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request, $params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $controller = new $class();
            $controller->$method($request, $params);
            return;
        }

        throw new \RuntimeException('Handler de route invalide');
    }
}
