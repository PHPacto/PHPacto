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

namespace PHPacto\Matcher\Rules;

use PHPacto\Matcher\Mismatches;

class CountItemsRule extends AbstractRule
{
    /**
     * @var ComparisonRule
     */
    protected $rule;

    public function __construct(ComparisonRule $rule, $sample = null)
    {
        $this->rule = $rule;

        parent::__construct($sample);
    }

    public function getRule(): ComparisonRule
    {
        return $this->rule;
    }

    public function assertMatch($test): void
    {
        if (!\is_array($test)) {
            throw new Mismatches\TypeMismatch('array', \gettype($test));
        }

        try {
            $this->rule->assertMatch(\count($test));
        } catch (Mismatches\Mismatch $mismatch) {
            throw new Mismatches\ValueMismatch('The items count in array {{ actual }} should match the rule:' . "\n" . '    {{ expected }}', $mismatch->getMessage(), \count($test));
        }
    }

    public function getSample()
    {
        if (null === $this->sample) {
            throw new \Exception('Count rule does not have a sample');
        }

        return $this->sample;
    }
}
