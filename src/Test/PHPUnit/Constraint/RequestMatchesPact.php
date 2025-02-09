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

namespace PHPacto\Test\PHPUnit\Constraint;

use PHPacto\Matcher\Mismatches\Mismatch;
use PHPacto\Matcher\Mismatches\MismatchCollection;
use PHPacto\PactInterface;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Constraint\Constraint as PHPUnitConstraint;
use PHPUnit\Util\InvalidArgumentHelper;
use Psr\Http\Message\RequestInterface;

class RequestMatchesPact extends PHPUnitConstraint
{
    /**
     * @var PactInterface
     */
    protected $pact;

    public function __construct(PactInterface $pact)
    {
        $this->pact = $pact;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate($request, $description = '', $returnResult = false): bool
    {
        try {
            $this->pact->getRequest()->assertMatch($request);
        } catch (Mismatch $mismatch) {
            if ($returnResult) {
                return false;
            }

            $this->failPactMatching($request, $mismatch, $description);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('request `%s` matches Pact', $this->pact->getDescription());
    }

    protected function failPactMatching(RequestInterface $request, Mismatch $mismatch, string $description = null)
    {
        $failureDescription = sprintf(
            'Failed asserting that %s',
            $this->failureDescription($request, $mismatch)
        );

        $additionalFailureDescription = $this->additionalFailureDescription($request);

        if ($additionalFailureDescription) {
            $failureDescription .= "\n" . $additionalFailureDescription;
        }

        if (!empty($description)) {
            $failureDescription = $description . "\n" . $failureDescription;
        }

        throw new AssertionFailedError(trim($failureDescription));
    }

    /**
     * {@inheritdoc}
     */
    protected function failureDescription($other, Mismatch $mismatch = null): string
    {
        $array_map_assoc = function (callable $f, array $a) {
            return array_column(array_map($f, array_keys($a), $a), 1, 0);
        };

        $func = function ($k, $v) {
            return [$k, $k . ":\n" . $v];
        };

        if ($mismatch instanceof MismatchCollection) {
            $mismatchesArray = $mismatch->toArrayFlat();

            return sprintf(
                "%s (%d rules failed)\n%s",
                $this->toString(),
                \count($mismatchesArray),
                implode("\n", $array_map_assoc($func, $mismatchesArray))
            );
        }

        return $this->toString() . ' ' . $mismatch->getMessage();
    }
}
