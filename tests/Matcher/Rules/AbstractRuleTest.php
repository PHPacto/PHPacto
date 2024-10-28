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

use PHPacto\Serializer\SerializerAwareTestCase;

class AbstractRuleTest extends SerializerAwareTestCase implements RuleTestCase
{
    public function test_it_has_a_sample()
    {
        /** @var Rule $rule */
        $rule = $this->getMockBuilder(AbstractRule::class)
            ->setConstructorArgs(['sample'])
            ->setMethodsExcept(['getSample'])
            ->getMock();

        self::assertEquals('sample', $rule->getSample());
    }

    public function test_it_is_normalizable()
    {
        $rule = $this->rule->hasSample('sample');

        $expected = [
            '_rule' => \get_class($rule),
            'sample' => 'sample',
        ];

        self::assertEquals($expected, $this->normalizer->normalize($rule));
    }
}
