<?php

namespace MkyCore\Router;

use MkyCore\Exceptions\Router\RouteCrudMethodNotExists;

class RouteCrud
{
    public function __construct(private readonly string $namespace, private array $routes)
    {
    }

    /**
     * @param array $methods
     * @return $this
     */
    public function as(array $methods): RouteCrud
    {
        foreach ($methods as $method => $name) {
            if (!isset($this->routes[$method])) {
                continue;
            }
            if (str_starts_with($name, '*')) {
                $name = str_replace('*', '', $name);
                $this->routes[$method]->as($this->routes[$method]->getName().$name);
            } elseif (str_ends_with($name, '*')) {
                $name = str_replace('*', '', $name);
                $this->routes[$method]->as($name.$this->routes[$method]->getName());
            } else {
                $this->routes[$method]->as($name);
            }
        }
        return $this;
    }

    /**
     * @param array $methods
     * @return $this
     */
    public function only(array $methods): static
    {
        $routes = [];
        foreach ($this->routes as $mt => $route){
            if(!in_array($mt, $methods)){
                $routes[] = $this->routes[$mt];
            }
        }
        for ($i = 0; $i < count($routes); $i ++){
            $route = $routes[$i];
            \MkyCore\Facades\Router::deleteRoute($route);
        }
        return $this;
    }

    /**
     * @param array $methods
     * @return $this
     */
    public function except(array $methods): static
    {
        $routes = [];
        foreach ($this->routes as $mt => $route){
            if(in_array($mt, $methods)){
                $routes[] = $this->routes[$mt];
            }
        }
        for ($i = 0; $i < count($routes); $i ++){
            $route = $routes[$i];
            \MkyCore\Facades\Router::deleteRoute($route);
        }
        return $this;
    }

    public function middlewares(array $methods): RouteCrud
    {
        if(isset($methods['*'])){
            $middlewares = $methods['*'];
            foreach($this->routes as $method => $route){
                $this->routes[$method]->middlewares($middlewares);
            }
            unset($methods['*']);
        }
        if($methods){
            foreach ($methods as $method => $middlewares){
                $oldMiddlewares = $this->routes[$method]->getMiddlewares();
                $this->routes[$method]->middlewares([...$oldMiddlewares, ...$middlewares]);
            }
        }
        return $this;
    }

    public function allows(array $methods): RouteCrud
    {
        if(isset($methods['*'])){
            $allows = $methods['*'];
            foreach($this->routes as $method => $route){
                $this->routes[$method]->allows($allows);
            }
            unset($methods['*']);
        }
        if($methods){
            foreach ($methods as $method => $allows){
                $oldMiddlewares = $this->routes[$method]->getPermissions();
                $this->routes[$method]->allows([...$oldMiddlewares, ...$allows]);
            }
        }

        return $this;
    }
}