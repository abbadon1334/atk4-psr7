<?php

declare(strict_types=1);

namespace Abbadon1334\Atk4\Psr7;

use Atk4\Ui\App;
use ErrorException;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\NonBufferedBody;
use Slim\ResponseEmitter;

class PSR7App extends App
{
    // never call exit, it will break response output
    public $call_exit = false;

    // App::run() must be called from middleware
    public $always_run = false;

    // Atk url builder remove default .ext // php
    public $url_building_ext = '';

    // Atk url builder remove default page // index
    public $page = '';

    private static ResponseInterface $server_response;

    private static array $_sent_headers = [];

    public $catch_exceptions = false;

    /**
     * @throws ErrorException
     */
    public function __construct($defaults = [])
    {
        self::$server_response = $defaults['response'] ?? new Response();

        // setup response object
        if (!isset($defaults['response'])) {
            unset($defaults['response']);
        }

        parent::__construct($defaults);
    }

    /**
     * Output Response to the client.
     */
    final protected function outputResponse(string $data, array $headers): void
    {
        $this->response_headers = $this->normalizeHeaders($this->response_headers);
        $headersAll = array_merge($this->response_headers, $this->normalizeHeaders($headers));
        $headersNew = array_diff_assoc($headersAll, self::$_sent_headers);

        $isSSE = ($headersAll['content-type'] ?? '') === 'text/event-stream';

        $lateError = null;
        foreach (ob_get_status(true) as $status) {
            if ($status['buffer_used'] !== 0) {
                $lateError = 'Unexpected output detected.';

                break;
            }
        }

        if ($lateError === null && count($headersNew) > 0 && !empty(self::$_sent_headers) && !$isSSE) {
            $lateError = 'Headers already sent, more headers cannot be set at this stage.';
        }

        if (empty(self::$server_response->getHeaders())) {
            if ($lateError !== null) {
                $headersNew = ['content-type' => 'text/plain', self::HEADER_STATUS_CODE => '500'];
            }

            foreach ($headersNew as $k => $v) {
                if ($k === static::HEADER_STATUS_CODE) {
                    self::$server_response = self::$server_response->withStatus($v === (string) (int) $v ? (int) $v : 500);
                } else {
                    $kCamelCase = $k;
                    preg_replace_callback('~(?<![a-zA-Z])[a-z]~', function ($matches) {
                        return strtoupper($matches[0]);
                    }, $k);

                    self::$server_response = self::$server_response->withHeader($kCamelCase, $v);
                }
                self::$_sent_headers[$k] = $v;
            }
        }

        if ($lateError !== null) {
            self::$server_response->getBody()->write("\n" . '!! FATAL UI ERROR: ' . $lateError . ' !!' . "\n");
            $this->callExit();
        }

        if ($isSSE) {
            if (!is_a(self::$server_response->getBody(), NonBufferedBody::class, true)) {
                self::$server_response = self::$server_response->withBody(new NonBufferedBody());
            }
            (new ResponseEmitter(1))->emit(self::$server_response);
        }

        self::$server_response->getBody()->write($data);
    }

    public function getResponse(): ResponseInterface
    {
        return self::$server_response;
    }
}
