<?php

namespace PhpMx;

use Closure;
use Exception;
use PhpMx\Router\RouterOverload;
use ReflectionMethod;

/**
 * @method static void get(string $route, string $response) Adiciona uma rota para interpretação em chamadas do tipo GET
 * @method static void get(string $route, string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo GET
 * @method static void get(string $response) Adiciona uma rota para interpretação em chamadas do tipo GET
 * @method static void get(string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo GET
 *
 * @method static void post(string $route, string $response) Adiciona uma rota para interpretação em chamadas do tipo POST
 * @method static void post(string $route, string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo POST
 * @method static void post(string $response) Adiciona uma rota para interpretação em chamadas do tipo POST
 * @method static void post(string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo POST
 *
 * @method static void put(string $route, string $response) Adiciona uma rota para interpretação em chamadas do tipo PUT
 * @method static void put(string $route, string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo PUT
 * @method static void put(string $response) Adiciona uma rota para interpretação em chamadas do tipo PUT
 * @method static void put(string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo PUT
 *
 * @method static void delete(string $route, string $response) Adiciona uma rota para interpretação em chamadas do tipo DELETE
 * @method static void delete(string $route, string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo DELETE
 * @method static void delete(string $response) Adiciona uma rota para interpretação em chamadas do tipo DELETE
 * @method static void delete(string $response, array $middlewares) Adiciona uma rota para interpretação em chamadas do tipo DELETE
 *
 * @method static void group(string $path, Closure $action) Adiciona path para um grupo de rotas
 * @method static void group(array $middlewares, Closure $action) Adiciona middleware para um grupo de rotas
 * @method static void group(string $path, array $middlewares, Closure $action) Adiciona path e middleware para um grupo de rotas
 * @method static void group(string $path, string $namespace, Closure $action) Adiciona path e namespace para um grupo de rotas
 * @method static void group(string $path, string $namespace, array $middlewares, Closure $action) Adiciona path, namespace e middleware para um grupo de rotas
 */
abstract class Router
{
    use RouterOverload;

    protected static array $ROUTE = [];
    protected static array $MIDDLEWARES = [[]];
    protected static array $NAMESPACE = [];
    protected static array $PATH = [];

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

    /** Explode uma rota em [template,params] */
    protected static function parseRouteTemplate(string $route): array
    {
        $params = [];
        $route = self::normalizeRoute($route);
        $route = explode('/', $route);

        foreach ($route as $pos => $param) {
            if (str_starts_with($param, '[')) {
                $param = trim($param, '[]');
                $type = substr($param, 0, 1);
                if (in_array($type, ['!', '#', '$', '@'])) {
                    $param = substr($param, 1);
                } else {
                    $type = '@';
                }
                if (empty($param)) $param = null;
                $params[$pos] = $param;
                $route[$pos] = $type;
            }
        }

        $route = implode('/', $route);
        return [$route, $params];
    }

    /** Limpa uma string para ser utilziada como rota */
    protected static function normalizeRoute(string $route): string
    {
        $route = explode('/', $route);
        $route = array_filter($route, fn($v) => trim($v) != '');
        $route = implode('/', $route);
        $route .= '/';

        if (strpos($route, '...') !== false) {
            $route = explode('...', $route);
            $route = array_shift($route) . '...';
        }

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
    /** Verifica se um template combina com a URL atual */
    protected static function checkRouteMatch(string $template): bool
    {
        $uri = Request::path();

        $template = trim($template, '/');
        $template = explode('/', $template);


        while (count($template)) {
            $expected = array_shift($template);
            $received = array_shift($uri) ?? '';

            if ($expected === '...') return true;

            if (is_blank($received) && !is_blank($expected)) return false;

            if ($expected === '!') {
                if (!is_numeric($received) || intval($received) != $received) return false;
            } elseif ($expected === '#') {
                if (!is_idKey($received)) return false;
            } elseif ($expected === '$') {
                if (!Cif::check($received)) return false;
            } elseif ($expected !== '@' && $received !== $expected) {
                return false;
            }
        }

        return count($uri) === 0;
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

            $dynamicParamWeight  =  ['!' => '1',  '#' => '2',  '$' => '3',  '@' => '4',  '...' => '5'];
            for ($i = 0; $i < $max; $i++) {
                $aVal = $arrayA[$i] ?? '';
                $bVal = $arrayB[$i] ?? '';
                $na .= $dynamicParamWeight[$aVal] ?? '0';
                $nb .= $dynamicParamWeight[$bVal] ?? '0';
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

    /** Adiciona uma rota para interpretação */
    protected static function defineRoute(string $route, string|int $response, array $middlewares): void
    {
        $route = implode('/', [...self::$PATH, $route]);

        if (is_string($response)) $response = implode('.', [...self::$NAMESPACE, $response]);

        list($template, $params) = self::parseRouteTemplate($route);

        $middlewares = [...end(self::$MIDDLEWARES), ...$middlewares];

        self::$ROUTE[$template] = [
            $template,
            $response,
            $params,
            $middlewares
        ];
    }

    /** Adiciona caminho, namespace e middlewares para um conjunto de rotas */
    protected static function defineGroup(?string $path, ?string $namespace, ?array $middlewares, Closure $action): void
    {
        $wrapper = $action;

        if ($namespace) $wrapper = fn() => self::defineCommonNamespace($namespace, $wrapper);
        if ($middlewares) $wrapper = fn() => self::defineCommonMiddleware($middlewares, $wrapper);
        if ($path) $wrapper = fn() => self::defineCommonPath($path, $wrapper);

        $wrapper();
    }

    /** Adiciona um caminho padrão para um conjunto de rotas */
    protected static function defineCommonPath(string $path, Closure $action): void
    {
        list($template) = self::parseRouteTemplate("$path...");
        $template = implode("/", [...self::$PATH, $template]);
        if (self::checkRouteMatch($template)) {
            self::$PATH[] = $path;
            $action();
            array_pop(self::$PATH);
        }
    }

    /** Adiciona um namespace padrão para um conjunto de rotas */
    protected static function defineCommonNamespace(string $namespace, Closure $action): void
    {
        $namespace = trim($namespace, '.');
        if (!is_blank($namespace)) {
            self::$NAMESPACE[] = $namespace;
            $action();
            array_pop(self::$NAMESPACE);
        }
    }

    /** Adiciona um middlewares padrão para um conjunto de rotas */
    protected static function defineCommonMiddleware(array $middlewares, Closure $action): void
    {
        if (!empty($middlewares)) {
            self::$MIDDLEWARES[] = [...end(self::$MIDDLEWARES), ...$middlewares];
            $action();
            array_pop(self::$MIDDLEWARES);
        }
    }
}
