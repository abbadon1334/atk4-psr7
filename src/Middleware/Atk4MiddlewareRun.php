<?php

namespace Satyr\Middleware;

use Atk4\Ui\Exception\ExitApplicationException;
use Psr\Container\ContainerInterface;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Satyr\Application;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\ResponseEmitter;

class Atk4MiddlewareRun implements MiddlewareInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        return $this->container->get(Application::class)->handleRequest($handler, $request);
    }
}