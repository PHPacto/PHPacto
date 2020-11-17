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

namespace PHPacto\Matcher\Rules;

use PHPacto\Matcher\Mismatches;

class StringEqualsRule extends StringComparisonRule
{
    public function __construct($value, $caseSensitive = true)
    {
        $this->assertSupport($value);

        $this->caseSensitive = $caseSensitive;
        $this->value = $value;
    }

    public function getSample()
    {
        return $this->value;
    }

    public function assertMatch($test): void
    {
        parent::assertMatch($test);

        if ($this->caseSensitive) {
            if ($this->value !== $test) {
                throw new Mismatches\ValueMismatch('String {{ actual }} should be equal to {{ expected }}', $this->value, $test);
            }
        } else {
            if (strtolower($this->value) !== strtolower($test)) {
                throw new Mismatches\ValueMismatch('String {{ actual }} should be equal to {{ expected }}', $this->value, $test);
            }
        }
    }

    protected function assertSupport(string $value): void
    {
    }
}
