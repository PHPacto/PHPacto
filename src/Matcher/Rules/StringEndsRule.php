<?php

/*
 * PHPacto - Contract testing solution
 *
 * Copyright (c) 2019  Damian Długosz
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

namespace Bigfoot\PHPacto\Matcher\Rules;

use Bigfoot\PHPacto\Matcher\Mismatches;

class StringEndsRule extends StringComparisonRule
{
    public function assertMatch($test): void
    {
        parent::assertMatch($test);

        $function = $this->caseSensitive ? 'strpos' : 'stripos';

        if (0 !== $function(strrev($test), strrev($this->value))) {
            throw new Mismatches\ValueMismatch('String {{ actual }} should end with {{ expected }}', $this->value, $test);
        }
    }
}
