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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PHPacto\Test\PHPUnit;

use PHPacto\Matcher\Mismatches\MismatchCollection;
use PHPacto\PactInterface;
use PHPacto\PactRequestInterface;
use PHPacto\PactResponseInterface;
use PHPacto\Test\PHPactoTestTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PHPactoTestCaseTest extends TestCase
{
    public function test_testcase_use_phpacto_trait()
    {
        $refl = new \ReflectionClass(PHPactoTestCase::class);
        self::assertArrayHasKey(PHPactoTestTrait::class, $refl->getTraits());
    }

    public function test_it_asserts_that_pact_matches_request()
    {
        $pact = $this->createMock(PactInterface::class);

        $pact->expects(self::once())
            ->method('getRequest')
            ->willReturn($request = $this->createMock(PactRequestInterface::class));

        $request->expects(self::once())
            ->method('assertMatch')
            ->with(self::isInstanceOf(RequestInterface::class));

        PHPactoTestCase::assertRequestMatchesPact($pact, $this->createMock(RequestInterface::class));
    }

    public function test_it_asserts_that_pact_matches_response()
    {
        $pact = $this->createMock(PactInterface::class);

        $pact->expects(self::once())
            ->method('getResponse')
            ->willReturn($request = $this->createMock(PactResponseInterface::class));

        $request->expects(self::once())
            ->method('assertMatch')
            ->with(self::isInstanceOf(ResponseInterface::class));

        PHPactoTestCase::assertResponseMatchesPact($pact, $this->createMock(ResponseInterface::class));
    }

    public function test_it_throws_assertion_error_if_request_not_match()
    {
        $pact = $this->createMock(PactInterface::class);

        $pact->expects(self::once())
            ->method('getRequest')
            ->willReturn($request = $this->createMock(PactRequestInterface::class));

        $request->expects(self::once())
            ->method('assertMatch')
            ->with(self::isInstanceOf(RequestInterface::class))
            ->willThrowException(new MismatchCollection([]));

        $this->expectException(AssertionFailedError::class);

        PHPactoTestCase::assertRequestMatchesPact($pact, $this->createMock(RequestInterface::class));
    }

    public function test_it_throws_assertion_error_if_response_not_match()
    {
        $pact = $this->createMock(PactInterface::class);

        $pact->expects(self::once())
            ->method('getResponse')
            ->willReturn($request = $this->createMock(PactResponseInterface::class));

        $request->expects(self::once())
            ->method('assertMatch')
            ->with(self::isInstanceOf(ResponseInterface::class))
            ->willThrowException(new MismatchCollection([]));

        $this->expectException(AssertionFailedError::class);

        PHPactoTestCase::assertResponseMatchesPact($pact, $this->createMock(ResponseInterface::class));
    }
}
