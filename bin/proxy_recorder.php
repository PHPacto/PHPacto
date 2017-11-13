<?php

/*
 * This file is part of PHPacto
 *
 * Copyright (c) 2017  Damian Długosz <bigfootdd@gmail.com>
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

use Bigfoot\PHPacto\Controller\ProxyController;
use Bigfoot\PHPacto\Logger\StdoutLogger;
use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require __DIR__.'/autoload.php';

$logger = new StdoutLogger();

$logger->log(sprintf(
    '[%s] %s: %s',
    date('Y-m-d H:i:s'),
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
));

if (!is_dir(CONTRACTS_DIR)) {
    mkdir(CONTRACTS_DIR, 0777, true);
}

if (!isset($_ENV['RECORDER_PROXY_TO'])) {
    throw new \Exception(sprintf('Environment variable "RECORDER_PROXY_TO" is not set.'));
}

define('PROXY_TO', parse_url(getenv('RECORDER_PROXY_TO')));

$httpClient = new Client();

$requestHandler = function (RequestInterface $request, Client $httpClient) use ($logger): ResponseInterface {
    $uri = $request->getUri()
        ->withScheme(@PROXY_TO['scheme'] ?: 'http')
        ->withHost(@PROXY_TO['host'] ?: 'localhost')
        ->withPort(@PROXY_TO['port'] ?: 'https' === @PROXY_TO['scheme'] ? 443 : 80);

    $controller = new ProxyController($httpClient, $logger, $uri, CONTRACTS_DIR);

    return $controller->action($request);
};

$server = Zend\Diactoros\Server::createServer($requestHandler, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
$server->listen();
