<?php

namespace PhpMx;

use Closure;
use Exception;
use ReflectionMethod;

abstract class Router
{
    protected static array $ROUTE = [];
    protected static array $MIDDLEWARES = [[]];
    protected static array $GROUP = [];

    /** Adiciona uma rota para interpretação em chamadas do tipo GET */
    static function get(string $route, int|string $response, array $middlewares = []): void
    {
        if (IS_GET) self::add(...func_get_args());
    }

    /** Adiciona uma rota para interpretação em chamadas do tipo POST */
    static function post(string $route, int|string $response, array $middlewares = []): void
    {
        if (IS_POST) self::add(...func_get_args());
    }

    /** Adiciona uma rota para interpretação em chamadas do tipo PUT */
    static function put(string $route, int|string $response, array $middlewares = []): void
    {
        if (IS_PUT) self::add(...func_get_args());
    }

    /** Adiciona uma rota para interpretação em chamadas do tipo DELETE */
    static function delete(string $route, int|string $response, array $middlewares = []): void
    {
        if (IS_DELETE) self::add(...func_get_args());
    }

    /** Adiciona middlewares globalmente ou para um conjunto de rotas */
    static function middleware(array $middlewares, ?Closure $action): void
    {
        self::$MIDDLEWARES[] = [...end(self::$MIDDLEWARES), ...$middlewares];
        $action();
        array_pop(self::$MIDDLEWARES);
    }

    /** Adiciona um grupo de rotas que serão declaradas apenas se o grupo for uma rota válida */
    static function group(string $group, array $middlewares, ?Closure $action = null): void
    {
        list($template) = self::parseRouteTemplate($group);

        $template = implode("/", [...self::$GROUP, $template]);

        if (self::checkRouteMatch("$template...")) {
            self::$GROUP[] = $group;
            self::middleware($middlewares, $action);
            array_pop(self::$GROUP);
        }
    }

    /** Resolve a requisição atual enviando a reposta ao cliente */
    static function solve(array $globalMiddlewares = [])
    {
        $paths = Path::seekDirs('routes');
        $paths = array_reverse($paths);

        foreach ($paths as $path)
            foreach (Dir::seekForFile($path, true) as $file)
                Import::only("$path/$file", true);

        $routeMatch = self::getRouteMatch();

        if ($routeMatch) {
            list($template, $response, $params, $middlewares) = $routeMatch;
            self::setRequestRouteParams($template, $params);
            $action = fn() => self::executeActionResponse($response, Request::data());
            $middlewares = [...$globalMiddlewares, ...$middlewares];
        } else {
            $action = fn() => throw new Exception('Route not found', STS_NOT_FOUND);
            $middlewares = $globalMiddlewares;
        }

        $response = Middleware::run($middlewares, $action);

        Response::content($response);
        Response::send();
    }

    /** Adiciona uma rota para interpretação */
    protected static function add(string $route, int|string $response, array $middlewares = []): void
    {
        $route = implode('/', [...self::$GROUP, $route]);

        list($template, $params) = self::parseRouteTemplate($route);

        $middlewares = [...end(self::$MIDDLEWARES), ...$middlewares];

        self::$ROUTE[$template] = [
            $template,
            $response,
            $params,
            $middlewares
        ];
    }

    /** Explode uma rota em [template,params] */
    protected static function parseRouteTemplate(string $route): array
    {
        $params = [];
        $route = self::normalizeRoute($route);
        $route = explode('/', $route);
        foreach ($route as $pos => $param)
            if (str_starts_with($param, '[#')) {
                $route[$pos] = '#';
                $param = substr($param, 2, -1);
                if (strpos($param, ':')) {
                    $route[$pos] = substr($param, strpos($param, ':') + 1);
                    $param = substr($param, 0, strpos($param, ':'));
                }
                if (empty($param))
                    $param = null;
                $params[$pos] = $param;
            }
        $route = implode('/', $route);
        return [$route, $params];
    }

    /** Limpa uma string para ser utilziada como rota */
    protected static function normalizeRoute(string $route): string
    {
        if (strpos($route, '?') !== false) {
            $paramsQuery = explode('?', $route);
            $route = array_shift($paramsQuery);
            $paramsQuery = implode('&', $paramsQuery);
            $paramsQuery = explode('&', $paramsQuery);
            asort($paramsQuery);
        }

        $route = trim($route, '/');

        $route .= '/';

        $route = str_replace(['[...]', '['], ['...', '[#'], $route);
        $route = str_replace(['[##', '[#='], ['[#', '[='], $route);

        $route = str_replace_all(['...', '.../', '......'], '/...', $route);
        $route = str_replace_all([' /', '//', '/ '], '/', $route);

        if ($paramsQuery ?? false)
            $route .= "?" . implode('?', $paramsQuery);

        return $route;
    }

    /** Retorna a rota que corresponde a URL atual */
    protected static function getRouteMatch(): ?array
    {
        $routes = self::organize(self::$ROUTE);
        foreach ($routes as $template => $route)
            if (self::checkRouteMatch($template))
                return $route;

        return null;
    }

    /** Organiza um array de rotas preparando para a interpretação */
    protected static function organize(array $array): array
    {
        uksort($array, function ($a, $b) {
            $nBarrA = substr_count($a, '/');
            $nBarrB = substr_count($b, '/');

            if ($nBarrA != $nBarrB) return $nBarrB <=> $nBarrA;

            $arrayA = explode('/', $a);
            $arrayB = explode('/', $b);
            $na = '';
            $nb = '';
            $max = max(count($arrayA), count($arrayB));

            for ($i = 0; $i < $max; $i++) {
                $na .= match (true) {
                    (($arrayA[$i] ?? '#') == '#') => '1',
                    (($arrayA[$i] ?? '') == '...') => '2',
                    default => '0'
                };
                $nb .= match (true) {
                    (($arrayB[$i] ?? '#') == '#') => '1',
                    (($arrayB[$i] ?? '') == '...') => '2',
                    default => '0'
                };
            }

            $result = intval($na) <=> intval($nb);

            if ($result) return $result;

            $result = count($arrayA) <=> count($arrayB);

            if ($result) return $result * -1;

            $result = strlen($a) <=> strlen($b);

            if ($result) return $result * -1;
        });
        return $array;
    }

    /** Verifica se um template combina com a URL atual */
    protected static function checkRouteMatch(string $template): bool
    {
        $template = self::normalizeRoute($template);

        list($template) = self::parseRouteTemplate($template);

        $uri = Request::path();

        $template = trim($template, '/');

        if (strpos($template, '?') !== false) {
            $paramsQuery = explode('?', $template);
            $template = array_shift($paramsQuery);
            foreach ($paramsQuery as $param)
                if (is_null(Request::query($param)))
                    return false;
        }

        $template = explode('/', $template);

        while (count($template)) {
            $esperado = array_shift($template);

            $recebido = array_shift($uri) ?? '';

            if ($recebido != $esperado) {

                if (is_blank($recebido)) return $esperado == '...';

                if ($esperado == '@') {
                    if (!is_numeric($recebido) || intval($recebido) != $recebido)
                        return false;
                } else if ($esperado != '#' && $esperado != '...') {
                    return false;
                }
            }

            if ($esperado == '...' && count($uri))
                $template[] = '...';
        }

        if (count($uri) != count($template))
            return false;

        return true;
    }

    /** Define os parametros da rota dentro do objeto de requisição */
    protected static function setRequestRouteParams(?string $template, ?array $params): void
    {
        if (is_null($template)) return;

        $uri = Request::path();
        $dataParams = [];

        foreach ($params ?? [] as $pos => $name) {
            $value = $uri[$pos];
            $dataParams[$name ?? count($dataParams)] = $value;
        }

        if (str_ends_with($template, '...')) {
            $template = explode('/', $template);
            array_pop($template);
            $dataParams = [...$dataParams, ...array_slice($uri, count($template))];
        }

        foreach ($dataParams as $var => $value)
            Request::set_route($var, $value);
    }

    /** Executa uma resposta de rota */
    protected static function executeActionResponse(string $response, array $data = [])
    {
        if (is_httpStatus($response))
            throw new Exception('', $response);

        if (is_int($response))
            throw new Exception('response route error', STS_INTERNAL_SERVER_ERROR);

        list($class, $method) = explode(':', "$response:default");

        $class = str_replace('.', '/', $class);
        $class = explode('/', $class);
        $class = array_map(fn($v) => ucfirst($v), $class);
        $class = path('Controller', ...$class);
        $class = str_replace('/', '\\', $class);

        if (!class_exists($class))
            throw new Exception('route not implemented', STS_NOT_IMPLEMENTED);

        $params = [];
        if (method_exists($class, '__construct')) {
            $reflection = new ReflectionMethod($class, '__construct');
            foreach ($reflection->getParameters() as $param) {
                $name = $param->getName();
                if (isset($data[$name])) {
                    $params[] = $data[$name];
                } else if ($param->isDefaultValueAvailable()) {
                    $params[] = $param->getDefaultValue();
                } else {
                    throw new Exception("Parameter [$name] is required", STS_INTERNAL_SERVER_ERROR);
                }
            }
        }

        $response = new $class(...$params);

        if (!method_exists($response, $method))
            throw new Exception("Method [$method] does not exist in response class", STS_NOT_IMPLEMENTED);

        $params = [];
        $reflection = new ReflectionMethod($response, $method);
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            if (isset($data[$name])) {
                $params[] = $data[$name];
            } else if ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                throw new Exception("Parameter [$name] is required", STS_INTERNAL_SERVER_ERROR);
            }
        }

        return $response->{$method}(...$params) ?? null;
    }
}
