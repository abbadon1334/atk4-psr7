<?php

namespace Satyr;

use Atk4\Ui\App;
use Atk4\Ui\Exception;
use Atk4\Ui\Exception\ExitApplicationException;
use ErrorException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\NonBufferedBody;
use Slim\ResponseEmitter;

class Application extends App
{
    public $call_exit        = false;
    public $always_run       = false;
    public $url_building_ext = '';

    private static ?ServerRequestInterface $serverRequest;
    private static ?ResponseInterface      $serverResponse;
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    /**
     * @throws ErrorException
     */
    public function __construct($defaults = [])
    {
        static::$serverRequest = $defaults['request'] ?? ServerRequestFactory::fromGlobals(
                $_SERVER,
                $_GET,
                $_POST,
                $_COOKIE,
                $_FILES
            );

        if (isset($defaults['request'])) {
            unset($defaults['request']);
        }

        static::$serverResponse = $defaults['response'] ?? new Response();

        if (isset($defaults['response'])) {
            unset($defaults['response']);
        }

        parent::__construct($defaults);
    }

    /**
     * Output Response to the client.
     *
     * This can be overridden for future PSR-7 implementation
     *
     * @throws ExitApplicationException
     */
    protected function outputResponse(string $data, array $headers): void
    {
        $is_sse = (static::$serverResponse->getHeader('content-type')[0] ?? '') == 'text/event-stream';

        $this->response_headers = $this->normalizeHeaders($this->response_headers);
        $headersAll = array_merge($this->response_headers, $this->normalizeHeaders($headers));
        $headersNew = array_diff_assoc($headersAll, static::$serverResponse->getHeaders());

        $lateError = null;
        foreach (ob_get_status(true) as $status) {
            if ($status['buffer_used'] !== 0) {
                $lateError = 'Unexpected output detected.';

                break;
            }
        }

        if ($lateError === null && count($headersNew) > 0 && headers_sent() && !$is_sse) {
            $lateError = 'Headers already sent, more headers cannot be set at this stage.';
        }

        if (!headers_sent()) {
            if ($lateError !== null) {
                $headersNew = ['content-type' => 'text/plain', self::HEADER_STATUS_CODE => '500'];
            }

            foreach ($headersNew as $k => $v) {
                if ($k === static::HEADER_STATUS_CODE) {
                    static::$serverResponse = static::$serverResponse->withStatus($v === (string)(int)$v ? (int)$v : 500);
                } else {
                    $kCamelCase = preg_replace_callback('~(?<![a-zA-Z])[a-z]~', function ($matches) {
                        return strtoupper($matches[0]);
                    }, $k);

                    static::$serverResponse = static::$serverResponse->withHeader($kCamelCase, $v);
                }
            }
        }

        if ($lateError !== null) {
            static::$serverResponse->getBody()->write("\n" . '!! FATAL UI ERROR: ' . $lateError . ' !!' . "\n");
            $this->callExit();
        }


        if ((static::$serverResponse->getHeader('content-type')[0] ?? '') == 'text/event-stream') {
            if (!is_a(static::$serverResponse->getBody(),NonBufferedBody::class, true)) {
                static::$serverResponse = static::$serverResponse->withBody(new NonBufferedBody());
                (new ResponseEmitter(1))->emit(static::$serverResponse);
            }
        }

        static::$serverResponse->getBody()->write($data);
    }

    /**
     * Override of App::url to remove "index" from default return
     */
    public function url($page = [], $needRequestUri = false, $extraRequestUriArgs = []): string
    {
        $uri = parent::url($page, $needRequestUri, $extraRequestUriArgs);

        $uri_parts = parse_url($uri);

        $path = explode("/", $uri_parts['path'] ?? '');
        if (end($path) === 'index') {
            array_pop($path);
            $path[] = '';
        }

        return implode("/", $path) . '?' . ($uri_parts['query'] ?? '');
    }

    public function handleRequest(\Psr\Http\Server\RequestHandlerInterface $handler, ServerRequestInterface $request) : ResponseInterface
    {
        try {

            $handler->handle($request);

            $this->run();

        } catch(ExitApplicationException $e) {
        }

        return static::$serverResponse;
    }

    public function getResponse() : ResponseInterface {
        return static::$serverResponse;
    }
}