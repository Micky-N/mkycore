<?php

namespace MkyCore\Middlewares;

use Exception;
use ReflectionException;
use ReflectionFunction;
use MkyCore\Application;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\Facades\Allows;
use MkyCore\Facades\Auth;
use MkyCore\Facades\Redirect;
use MkyCore\Interfaces\MiddlewareInterface;
use MkyCore\Interfaces\ResponseHandlerInterface;
use MkyCore\Request;
use MkyCore\Router\Route;

class PermissionMiddleware implements MiddlewareInterface
{

    private array $permissions = [];

    private int $index = 0;


    public function __construct(private readonly Application $app)
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process(Request $request, callable $next): ResponseHandlerInterface
    {
        $route = $request->getAttribute(Route::class);
        if ($route) {
            $module = $route->getModule();
            $appServiceProvider = $this->getAuthServiceProvider($module);
            if(class_exists($appServiceProvider)){
                $appServiceProvider->register();
                $this->permissions = $route->getPermissions();
                if (!$this->processPermission($route)) {
                    return Redirect::error(401);
                }
            }
        }
        return $next($request);
    }

    /**
     * @param mixed $module
     * @return mixed
     * @throws ReflectionException
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     */
    private function getAuthServiceProvider(mixed $module): mixed
    {
        $modulePath = $this->app->getModulePath($module);
        $modulePath .= DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AuthServiceProvider.php';
        $authServiceProvider = ucfirst(trim(str_replace([$this->app->get('path:base'), '.php', DIRECTORY_SEPARATOR], ['', '', '\\'], $modulePath), DIRECTORY_SEPARATOR . '/'));
        return $this->app->get($authServiceProvider);
    }

    /**
     * @param Route $route
     * @return bool|mixed
     * @throws ReflectionException
     */
    private function processPermission(Route $route): mixed
    {
        $permission = $this->getPermission();
        if ($this->index >= count($this->permissions) && !$permission) {
            return true;
        }
        $vars = explode(':', $permission);
        $name = $vars[0];
        $variable = $vars[1] ?? null;
        if ($this->handlePermission($route, $name, $variable)) {
            return call_user_func([$this, 'processPermission'], $route);
        }
        return false;
    }

    public function getPermission(): ?string
    {
        $permission = $this->permissions[$this->index] ?? null;
        $this->index++;
        return $permission;
    }

    /**
     * @param Route $route
     * @param string $name
     * @param string|null $variable
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    private function handlePermission(Route $route, string $name, ?string $variable = null): bool
    {
        $routeParams = $route->getParams();
        $callback = Allows::getCallback($name);
        if (!$callback) {
            throw new Exception("Permission $name not found");
        }
        $callbackReflection = new ReflectionFunction($callback);
        if (!($auth = Auth::user())) {
            return false;
        }
        $authClass = get_class($auth);
        $entity = null;
        if ($variable) {
            $parameterReflection = array_filter($callbackReflection->getParameters(), function ($parameter) use ($authClass) {
                return $parameter->getType()->getName() !== $authClass;
            });
            $parameterReflection = reset($parameterReflection);
            $typeEntity = $parameterReflection->getType()->getName();
            $valueParam = $routeParams[$variable];
            $entity = $this->app->getInstanceEntity($typeEntity, $valueParam);
        }
        return $callback($auth, $entity) ?? false;
    }
}