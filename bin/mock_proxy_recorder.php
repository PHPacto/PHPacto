<?php

/*
 * PHPacto - Contract testing solution
 *
 * Copyright (c) 2018  Damian Długosz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use Bigfoot\PHPacto\Controller\MockProxyController;
use Bigfoot\PHPacto\Logger\StdoutLogger;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

require __DIR__ . '/bootstrap.php';

if (false !== ($allowOrigin = \getenv('ALLOW_ORIGIN'))) {
    if ('all' === \strtolower($allowOrigin)) {
        $allowOrigin = '*';
    }
} else {
    $allowOrigin = null;
}

$logger = new StdoutLogger();

if (!\is_dir(CONTRACTS_DIR)) {
    \mkdir(CONTRACTS_DIR, 0777, true);
}

if (!\getenv('RECORDER_PROXY_TO')) {
    throw new \Exception(\sprintf('Environment variable "RECORDER_PROXY_TO" is not set.'));
}

$httpClient = new Client();
$controller = new MockProxyController($httpClient, $logger, \getenv('RECORDER_PROXY_TO'), CONTRACTS_DIR);

$handler = function(RequestInterface $request) use ($logger, $controller, $allowOrigin): ResponseInterface {
    if (
        isset($allowOrigin)
        && 'OPTIONS' === $request->getMethod()
        && $request->hasHeader('Access-Control-Request-Method')
    ) {
        $stream = new Stream('php://memory', 'r');

        return new Response($stream, 201, [
            'Access-Control-Allow-Credentials' => 'True',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD',
            'Access-Control-Allow-Headers' => '*',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    $logger->log(\sprintf(
        '[%s] %s: %s',
        \date('Y-m-d H:i:s'),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI']
    ));

    try {
        $response = $controller->action($request);

        $logger->log(\sprintf('Pact responded with Status Code %d', $response->getStatusCode()));

        if (null !== $this->allowOrigin) {
            $response = $response
                ->withHeader('Access-Control-Allow-Credentials', 'True')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD')
                ->withHeader('Access-Control-Allow-Headers', '*')
                ->withHeader('Access-Control-Allow-Origin', $allowOrigin);
        }

        return $response;
    } catch (\Throwable $t) {
        function throwableToArray(\Throwable $t): array
        {
            return [
                'message' => $t->getMessage(),
                'trace' => $t->getTrace(),
                'line' => $t->getLine(),
                'file' => $t->getFile(),
                'code' => $t->getCode(),
                'previous' => $t->getPrevious() ? throwableToArray($t->getPrevious()) : null,
            ];
        };

        $stream = new Stream('php://memory', 'rw');
        $stream->write(json_encode(throwableToArray($t)));

        $logger->log($t->getMessage());

        return new Response($stream, 418, ['Content-type' => 'application/json']);
    }
};

$server = Zend\Diactoros\Server::createServer($handler, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
$server->listen();
