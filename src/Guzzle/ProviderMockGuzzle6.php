<?php

/*
 * PHPacto - Contract testing solution
 *
 * Copyright (c) Damian Długosz
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 */

namespace PHPacto\Guzzle;

use PHPacto\PactInterface;
use PHPacto\Test\PHPactoTestTrait;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ProviderMockGuzzle6 implements ProviderMock
{
    use PHPactoTestTrait;

    /**
     * @var MockHandler
     */
    private $mock;

    public function __construct()
    {
        $this->mock = new MockHandler();
    }

    public function handlePact(PactInterface $pact): void
    {
        $this->mock->append(function (RequestInterface $request) use ($pact): ResponseInterface {
            self::assertRequestMatchesPact($pact, $request);

            return $pact->getResponse()->getSample();
        });
    }

    public function getHandler(): HandlerStack
    {
        return HandlerStack::create($this->mock);
    }
}
