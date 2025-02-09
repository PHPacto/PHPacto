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

namespace PHPacto\Bin;

use PHPUnit\Framework\TestCase;

class PactoTest extends TestCase
{
    public function test_it_not_fails()
    {
        exec('bin/phpacto', $output, $exitCode);

        self::assertEquals(0, $exitCode);
        self::assertStringContainsString('PHPacto', $output[0]);
    }

    public function test_it_has_copyright()
    {
        $output = `bin/phpacto 2>&1`;

        self::assertStringContainsString('Copyright (c) ', $output);
    }
}
