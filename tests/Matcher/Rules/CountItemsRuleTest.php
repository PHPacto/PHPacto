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
use PHPacto\Serializer\SerializerAwareTestCase;

class CountItemsRuleTest extends SerializerAwareTestCase
{
    public function test_it_is_normalizable()
    {
        $childRule = $this->rule->empty(ComparisonRule::class);
        $rule = new CountItemsRule($childRule, []);

        $expected = [
            '_rule' => 'count',
            'rule' => ['_rule' => \get_class($childRule)],
        ];

        self::assertEquals($expected, $this->normalizer->normalize($rule));
    }

    public function test_it_is_denormalizable()
    {
        $childRule = $this->rule->empty(ComparisonRule::class);

        $data = [
            '_rule' => 'count',
            'rule' => ['_rule' => \get_class($childRule)],
            'sample' => [],
        ];

        $rule = $this->normalizer->denormalize($data, Rule::class);

        self::assertInstanceOf(CountItemsRule::class, $rule);
        self::assertSame([], $rule->getSample());
    }

    public function matchesTrueProvider()
    {
        return [
            [true, new EqualsRule(0), []],
            [true, new GreaterRule(4), [null, false, true, 0, '']],
        ];
    }

    public function matchesFalseProvider()
    {
        return [
            [false, new EqualsRule(0), ''],
            [false, new LowerRule(0), []],
        ];
    }

    /**
     * @dataProvider matchesTrueProvider
     * @dataProvider matchesFalseProvider
     *
     * @param mixed $ruleValue
     * @param mixed $testValue
     */
    public function testMatch(bool $shouldMatch, $ruleValue, $testValue)
    {
        $rule = new CountItemsRule($ruleValue);

        if (!$shouldMatch) {
            $this->expectException(Mismatches\Mismatch::class);
        }

        $rule->assertMatch($testValue);

        self::assertTrue(true, 'No exceptions should be thrown if matching');
    }
}
