<?php declare(strict_types=1);

include 'vendor/autoload.php';

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->addDefinitions([
    \Satyr\Application::class => DI\create()
        ->method('initLayout', [\Atk4\Ui\Layout\Centered::class])
]);
$container = $containerBuilder->build();

$app = \DI\Bridge\Slim\Bridge::create($container);

$app->addMiddleware(new \Satyr\Middleware\Atk4MiddlewareRun($container));
$app->addErrorMiddleware(true, true, true);

$app->map(['GET', 'POST'], '/favicon.ico', function(Request $request, Response $response): Response {
    return $response;
});

$app->map(['GET', 'POST'], '/', function(Request $request, Response $response, array $args = [], \Satyr\Application $app = null) {

    \Atk4\Ui\Header::addTo($app, ['SSE with ProgressBar']);

    $bar = \Atk4\Ui\ProgressBar::addTo($app);

    $buttonTest = \Atk4\Ui\Button::addTo($app, ['Turn Test']);
    $button = \Atk4\Ui\Button::addTo($app, ['Turn On']);
    $buttonStop = \Atk4\Ui\Button::addTo($app, ['Turn Off']);

    // non-SSE way
    $buttonTest->on('click', function($jq) use ($bar) {
        error_log("progress");
        return $bar->js()->progress(['percent' => 40]);
    });

    $sse = \Atk4\Ui\JsSse::addTo($app, [
        'showLoader' => true
    ]);

    $button->on('click', $sse->set(function () use ($button, $sse, $bar) {
        error_log("click");
        $sse->send($button->js()->addClass('disabled'));

        $sse->send($bar->jsValue(20));
        sleep(1);
        $sse->send($bar->jsValue(40));
        sleep(1);
        $sse->send($bar->jsValue(60));
        sleep(2);
        $sse->send($bar->jsValue(80));
        sleep(1);

        // non-SSE way
        return [
            $bar->jsValue(100),
            $button->js()->removeClass('disabled'),
        ];
    }));

    $buttonStop->on('click', [$button->js()->atkServerEvent('stop'), $button->js()->removeClass('disabled')]);

    return $response;
});

$app->run();