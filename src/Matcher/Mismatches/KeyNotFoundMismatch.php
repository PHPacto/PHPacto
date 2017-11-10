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

namespace Bigfoot\PHPacto\Matcher\Mismatches;

class KeyNotFoundMismatch extends Mismatch
{
    private $key;

    /**
     * @param string $message
     * @param mixed  $expected
     * @param mixed  $actual
     */
    public function __construct(string $key)
    {
        $this->message = sprintf('Key `%s` was not found', $key);
        $this->key = $key;
    }

    /**
     * @return mixed
     */
    public function getExpected()
    {
        return $this->key;
    }
}
