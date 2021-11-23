<?php

declare(strict_types=1);

namespace Abbadon1334\Atk4\Psr7\Middleware;

use Atk4\Ui\Exception\ExitApplicationException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Abbadon1334\Atk4\Psr7\PSR7App;

class Atk4RunMiddleware implements MiddlewareInterface
{
    private ContainerInterface $container;

    private PSR7App $atk;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->atk = $this->container->get(PSR7App::class);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $response = $handler->handle($request);

            // if not initialized means Application was not involved
            if (empty($this->atk->html)) {
                return $response;
            }

            $this->atk->run();
        } catch (ExitApplicationException $e) {
        }

        return $this->atk->getResponse();
    }
}
