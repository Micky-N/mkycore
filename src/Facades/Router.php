<?php

namespace MkyCore\Facades;

use MkyCore\Abstracts\Facade;
use MkyCore\Router\Route;

/**
 * @method static \MkyCore\Router\Router get(string $url, callable|array $action, string $module = 'root')
 * @method static \MkyCore\Router\Router post(string $url, callable|array $action, string $module = 'root')
 * @method static \MkyCore\Router\Router put(string $url, callable|array $action, string $module = 'root')
 * @method static \MkyCore\Router\Router delete(string $url, callable|array $action, string $module = 'root')
 * @method static \MkyCore\Router\RouteCrud crud(string $namespace, string $controller, string $moduleName = 'root')
 * @method static \MkyCore\Router\Route getCurrentRoute()
 * @method static array getRoutes(array $filters = [])
 * @method static void deleteRoute(Route $route)
 * @method static string getUrlFromName(string $name, array $params = [], bool $absolute = true)
 * @see \MkyCore\Router\Router
 */
class Router extends Facade
{
    protected static string $accessor = \MkyCore\Router\Router::class;
}