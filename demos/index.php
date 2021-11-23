<?php

declare(strict_types=1);

include __DIR__ . '/../vendor/autoload.php';

use Atk4\Ui\Button;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\JsModal;
use Atk4\Ui\JsSse;
use Atk4\Ui\JsToast;
use Atk4\Ui\Layout\Centered;
use Atk4\Ui\LoremIpsum;
use Atk4\Ui\Message;
use Atk4\Ui\Modal;
use Atk4\Ui\ProgressBar;
use Atk4\Ui\Text;
use Atk4\Ui\View;
use Atk4\Ui\VirtualPage;
use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Abbadon1334\Atk4\Psr7\Middleware\Atk4RunMiddleware;
use Abbadon1334\Atk4\Psr7\PSR7App;

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'app.seed' => [
        'title' => 'PSR7 Test',
    ],
    PSR7App::class => DI\create()->constructor(DI\get('app.seed')),
    LoggerInterface::class => DI\create(\Psr\Log\NullLogger::class),
]);

$container = $containerBuilder->build();

$slimApp = Bridge::create($container);

$slimApp->addMiddleware(new Atk4RunMiddleware($container));
$error = $slimApp->addErrorMiddleware(true, true, true);

$customErrorHandler = function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails,
    LoggerInterface $logger = null
) use ($container) {
    $app = $container->get(PSR7App::class);
    $app->caughtException($exception);

    return $app->getResponse();
};

$error->setDefaultErrorHandler($customErrorHandler);

$slimApp->map(['GET', 'POST'], '/favicon.ico', function (Request $request, Response $response): Response {
    return $response;
});

$slimApp->map(
    ['GET', 'POST'],
    '/',
    function (Request $request, Response $response, array $args = [], Abbadon1334\Atk4\Psr7\PSR7App $app = null) {
        $app->initLayout([Centered::class]);

        Header::addTo($app, ['SSE with ProgressBar']);

        $bar = ProgressBar::addTo($app);

        $buttonTest = Button::addTo($app, ['Turn Test']);
        $button = Button::addTo($app, ['Turn On']);
        $buttonStop = Button::addTo($app, ['Turn Off']);

        // non-SSE way
        $buttonTest->on('click', null, function ($jq) use ($bar) {
            return [
                $bar->js()->progress(['percent' => 40]),
            ];
        });

        $sse = JsSse::addTo($app, [
            'showLoader' => true,
        ]);

        $button->on('click', $sse->set(function () use ($button, $sse, $bar) {
            error_log('click');
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

        $buttonStop->on('click', null, [
            $button->js()->atkServerEvent('stop'),
            $button->js()->removeClass('disabled'),
        ]);

        View::addTo($app, [
            'Forms below demonstrate how to work with multi-value selectors',
            'ui' => 'ignored warning message',
        ]);

        $cc = Columns::addTo($app);
        $form = Form::addTo($cc->addColumn());

        $form->addControl('one', [], ['enum' => ['female', 'male']])->set('male');
        $form->addControl('two', [Form\Control\Radio::class], ['enum' => ['female', 'male']])->set('male');

        $form->addControl('three', [], ['values' => ['female', 'male']])->set(1);
        $form->addControl('four', [Form\Control\Radio::class], ['values' => ['female', 'male']])->set(1);

        $form->addControl('five', [], ['values' => [5 => 'female', 7 => 'male']])->set(7);
        $form->addControl('six', [Form\Control\Radio::class], ['values' => [5 => 'female', 7 => 'male']])->set(7);

        $form->addControl('seven', [], ['values' => ['F' => 'female', 'M' => 'male']])->set('M');
        $form->addControl(
            'eight',
            [Form\Control\Radio::class],
            ['values' => ['F' => 'female', 'M' => 'male']]
        )->set('M');

        $form->onSubmit(function (Form $form) use ($app) {
            return new JsToast($app->encodeJson($form->model->get()));
        });

        // define virtual page.
        $virtualPage = VirtualPage::addTo($app->layout, ['urlTrigger' => 'in']);

        // Add content to virtual page.
        if (isset($_GET['p_id'])) {
            Header::addTo($virtualPage, [$_GET['p_id']])->addClass('__atk-behat-test-car');
        }
        LoremIpsum::addTo($virtualPage, ['size' => 1]);
        $virtualPageButton = Button::addTo($virtualPage, ['Back', 'icon' => 'left arrow']);
        $virtualPageButton->link('/');
        $virtualPage->ui = 'grey inverted segment';

        $modal = Modal::addTo($virtualPage);
        $modal->set(function ($modal) {
            Text::addTo($modal)->set('This is yet another modal');
            LoremIpsum::addTo($modal, ['size' => 2]);
        });
        $button = Button::addTo($virtualPage)->set('Open Lorem Ipsum');
        $button->on('click', $modal->show());

        $msg = Message::addTo($app, ['Virtual Page']);
        $msg->text->addParagraph('Virtual page content are not rendered on page load. They will ouptput their content when trigger.');
        $msg->text->addParagraph('Click button below to trigger it.');

        // button that trigger virtual page.
        $btn = Button::addTo($app, ['More info on Car']);
        $btn->link($virtualPage->cb->getUrl() . '&p_id=Car');

        $btn = Button::addTo($app, ['More info on Bike']);
        $btn->link($virtualPage->cb->getUrl() . '&p_id=Bike');

        // Test 1 - Basic reloading
        Header::addTo($app, ['Virtual Page Logic']);

        $virtualPage = VirtualPage::addTo($app); // this page will not be visible unless you trigger it specifically
        View::addTo($virtualPage, ['Contents of your pop-up here'])->addClass('ui header __atk-behat-test-content');
        LoremIpsum::addTo($virtualPage, ['size' => 2]);

        View::addTo($virtualPage, ['ui' => 'hidden divider']);
        Button::addTo($virtualPage, ['Back', 'icon' => 'left arrow'])->link('/');

        $bar = View::addTo($app, ['ui' => 'buttons']);
        Button::addTo($bar)->set('Inside current layout')->link($virtualPage->getUrl());
        Button::addTo($bar)->set('On a blank page')->link($virtualPage->getUrl('popup'));
        Button::addTo($bar)->set('No layout at all')->link($virtualPage->getUrl('cut'));

        Header::addTo(
            $app,
            ['Inside Modal', 'subHeader' => 'Virtual page content can be display using JsModal Class.']
        );

        $bar = View::addTo($app, ['ui' => 'buttons']);
        Button::addTo($bar)->set('Load in Modal')->on(
            'click',
            null,
            new JsModal('My Popup Title', $virtualPage->getJsUrl('cut'))
        );

        Button::addTo($bar)->set('Simulate slow load')->on(
            'click',
            null,
            new JsModal('My Popup Title', $virtualPage->getJsUrl('cut') . '&slow=true')
        );
        if (isset($_GET['slow'])) {
            sleep(1);
        }

        Button::addTo($bar)->set('No title')
            ->on('click', null, new JsModal('', $virtualPage->getJsUrl('cut')));

        return $response;
    }
);

$slimApp->map(
    ['GET', 'POST'],
    '/test/exceptions',
    function (Request $request, Response $response, array $args = [], Abbadon1334\Atk4\Psr7\PSR7App $app = null) {

        $app->initLayout([\Atk4\Ui\Layout::class]);

        Button::addTo($app, ['test ui exception'])->on('click', null, function () {
            throw new \Atk4\Ui\Exception('Ui exception triggered');
        });

        Button::addTo($app, ['test error'])->on('click', null, function () {
            throw new Error('error triggered');
        });

        return $app->getResponse();
    }
);

$slimApp->map(
    ['GET', 'POST'],
    '/test/html-error',
    function (Request $request, Response $response, array $args = [], Abbadon1334\Atk4\Psr7\PSR7App $app = null) {

        $app->initLayout([\Atk4\Ui\Layout::class]);

        throw new \Atk4\Ui\Exception('Ui exception triggered');

    }
);

$slimApp->run();
