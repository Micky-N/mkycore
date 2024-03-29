<?php

namespace MkyCore\Middlewares;

use MkyCore\Application;
use MkyCore\Config;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\Interfaces\MiddlewareInterface;
use MkyCore\Request;
use MkyCore\Router\Route;
use MkyCore\Router\Router;
use ReflectionException;

class RouterMiddleware implements MiddlewareInterface
{

    public function __construct(private readonly Application $app, private readonly Router $router)
    {
    }

    /**
     * @param Request $request
     * @param callable $next
     * @return mixed
     * @throws ReflectionException
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     */
    public function process(Request $request, callable $next): mixed
    {
        $route = $this->router->match($request);
        if (!$route) {
            return $next($request);
        }

        $params = $route->getParams();

        $request = array_reduce(array_keys($params), function (Request $request, $key) use ($params) {
            return $request->withAttribute($key, $params[$key]);
        }, $request->withAttribute(get_class($route), $route));
        $this->app->setCurrentRoute($route);
        return $next($request);
    }

    private function getModuleFromRoute(Route $route): string
    {
        return $route->getModule();
    }
}