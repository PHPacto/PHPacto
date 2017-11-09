<?php

/*
 * This file is part of PHPacto
 *
 * Copyright (c) 2017  Damian Długosz
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

class CountRule extends AbstractRule
{
    /**
     * @var Rule
     */
    private $rule;

    public function __construct(Rule $rule, $sample = null)
    {
        parent::__construct($sample);

        $this->rule = $rule;

        if (null !== $sample) {
            $this->assertMatch($sample);
        }
    }

    public function getRule(): Rule
    {
        return $this->rule;
    }

    public function assertMatch($test): void
    {
        if (!is_array($test)) {
            throw new Mismatches\TypeMismatch('array', gettype($test));
        }

        try {
            $this->rule->assertMatch(count($test));
        } catch (Mismatches\Mismatch $mismatch) {
            throw new Mismatches\ValueMismatch(
                'The items count in array {{ actual }} should match the rule:'."\n".
                '    {{ expected }}',
                $mismatch->getMessage(),
                count($test)
            );
        }
    }
}
