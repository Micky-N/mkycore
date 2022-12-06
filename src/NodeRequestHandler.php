<?php

namespace MkyCore;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use MkyCore\Exceptions\Container\FailedToResolveContainerException;
use MkyCore\Exceptions\Container\NotInstantiableContainerException;
use MkyCore\Interfaces\MiddlewareInterface;
use MkyCore\Middlewares\CsrfMiddleware;
use MkyCore\Middlewares\DispatcherMiddleware;
use MkyCore\Middlewares\GlobalHandlerMiddleware;
use MkyCore\Middlewares\MethodMiddleware;
use MkyCore\Middlewares\ModuleHandlerMiddleware;
use MkyCore\Middlewares\NotFoundMiddleware;
use MkyCore\Middlewares\PermissionMiddleware;
use MkyCore\Middlewares\ResponseHandlerNotFound;
use MkyCore\Middlewares\RouteHandlerMiddleware;
use MkyCore\Middlewares\RouterMiddleware;
use MkyCore\Middlewares\TrailingSlashMiddleware;
use MkyCore\Middlewares\WhoopsHandlerMiddleware;

class NodeRequestHandler implements RequestHandlerInterface
{

    private array $nodeMiddlewares;
    private int $index = 0;

    public function __construct(private readonly Application $app)
    {
        $this->setInitNodeMiddlewares();
    }

    private function setInitNodeMiddlewares(): void
    {
        $this
            ->setMiddleware(WhoopsHandlerMiddleware::class)
            ->setMiddleware(TrailingSlashMiddleware::class)
            ->setMiddleware(MethodMiddleware::class)
            ->setMiddleware(CsrfMiddleware::class)
            ->setMiddleware(GlobalHandlerMiddleware::class)
            ->setMiddleware(RouterMiddleware::class)
            ->setMiddleware(ModuleHandlerMiddleware::class)
            ->setMiddleware(RouteHandlerMiddleware::class)
            ->setMiddleware(PermissionMiddleware::class)
            ->setMiddleware(DispatcherMiddleware::class)
            ->setMiddleware(NotFoundMiddleware::class);
    }

    public function setMiddleware(string $middleware): NodeRequestHandler
    {
        $this->nodeMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     * @throws ReflectionException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return Response::getFromHandler($this->process($request));
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function process(Request $request): mixed
    {
        try {
            $middleware = $this->getCurrentMiddleware();
            return $middleware->process($request, [$this, 'process']);
        } catch (\Exception $exception) {
            if (env('APP_ENV', 'local') === 'local') {
                throw $exception;
            }
            return new ResponseHandlerNotFound(status: 500, reason: $exception->getMessage());
        }
    }

    /**
     * @return ResponseInterface|MiddlewareInterface
     * @throws FailedToResolveContainerException
     * @throws NotInstantiableContainerException
     * @throws ReflectionException
     */
    private function getCurrentMiddleware(): ResponseInterface|MiddlewareInterface
    {
        if (isset($this->nodeMiddlewares[$this->index])) {
            $middleware = $this->nodeMiddlewares[$this->index];
            $this->index++;
            return $this->app->get($middleware);
        } else {
            return new ResponseHandlerNotFound();
        }
    }
}