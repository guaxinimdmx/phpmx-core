<?php

namespace PhpMx\Router;

trait RouterOverload
{
  /** @ignore */
  static function get(string|int $route, int|string|array $response = STS_NOT_IMPLEMENTED, array $middlewares = []): void
  {
    if (IS_GET) self::add(...func_get_args());
  }

  /** @ignore */
  static function post(string|int $route, int|string|array $response = STS_NOT_IMPLEMENTED, array $middlewares = []): void
  {
    if (IS_POST) self::add(...func_get_args());
  }

  /** @ignore */
  static function put(string|int $route, int|string|array $response = STS_NOT_IMPLEMENTED, array $middlewares = []): void
  {
    if (IS_PUT) self::add(...func_get_args());
  }

  /** @ignore */
  static function delete(string|int $route, int|string|array $response = STS_NOT_IMPLEMENTED, array $middlewares = []): void
  {
    if (IS_DELETE) self::add(...func_get_args());
  }

  /** @ignore */
  protected static function add(...$args): void
  {
    $route = '';
    $response = null;
    $middlewares = [];

    $nArgs = count($args);

    if ($nArgs == 1)
      list($response) = $args;

    elseif ($nArgs == 2 && is_string($args[1]))
      list($route, $response) = $args;

    elseif ($nArgs == 2 && is_array($args[1]))
      list($response, $middlewares) = $args;

    elseif ($nArgs == 3)
      list($route, $response, $middlewares) = $args;

    self::defineRoute($route, $response, $middlewares);
  }


  /** @ignore */
  static function group(...$args): void
  {
    $path = '';
    $namespace = '';
    $middlewares = [];
    $action = array_pop($args);

    $nArgs = count($args);

    if ($nArgs == 1 && is_string($args[0]))
      list($path) = $args;

    elseif ($nArgs == 1 && is_array($args[0]))
      list($middlewares) = $args;

    elseif ($nArgs == 2 && is_string($args[1]))
      list($path, $namespace) = $args;

    elseif ($nArgs == 2 && is_array($args[1]))
      list($path, $middlewares) = $args;

    elseif ($nArgs == 3 && is_array($args[1]))
      list($path, $namespace, $middlewares) = $args;

    self::defineGroup($path, $namespace, $middlewares, $action);
  }
}
