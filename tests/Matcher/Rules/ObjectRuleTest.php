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

class ObjectRuleTest extends SerializerAwareTestCase
{
    public function test_it_has_a_default_sample()
    {
        $rule = new ObjectRule(['property' => $this->rule->hasSample('one')]);

        $expected = [
            'property' => 'one',
        ];

        self::assertSame($expected, $rule->getSample());
    }

    public function test_it_is_normalizable()
    {
        $childRule = $this->rule->empty();
        $rule = new ObjectRule(['prop' => $childRule], ['prop' => 'value']);

        $expected = [
            '_rule' => 'object',
            'properties' => [
                'prop' => ['_rule' => \get_class($childRule)],
            ],
            'sample' => [
                'prop' => 'value',
            ],
        ];

        self::assertEquals($expected, $this->normalizer->normalize($rule));
    }

    public function test_it_is_denormalizable()
    {
        $childRule = $this->rule->empty();

        $data = [
            '_rule' => 'object',
            'properties' => [
                'prop' => ['_rule' => \get_class($childRule)],
            ],
            'sample' => [
                'prop' => 'value',
            ],
        ];

        $rule = $this->normalizer->denormalize($data, Rule::class);

        self::assertInstanceOf(ObjectRule::class, $rule);
        self::assertSame(['prop' => 'value'], $rule->getSample());
    }

    public function test_it_is_denormalizable_empty()
    {
        $data = [
            '_rule' => 'object',
            'sample' => [],
        ];

        $rule = $this->normalizer->denormalize($data, Rule::class);

        self::assertInstanceOf(ObjectRule::class, $rule);
        self::assertSame([], $rule->getSample());
    }

    public function matchesTrueProvider()
    {
        $two = new EqualsRule(2);

        return [
            [true, [], []],
            [true, ['two' => $two], ['one' => 1, 'two' => 2, 'three' => 3]],
        ];
    }

    public function matchesFalseProvider()
    {
        $zero = new EqualsRule(0);

        return [
            [false, ['zero' => $zero], []],
            [false, ['zero' => $zero], ['zero' => 1]],
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
        $rule = new ObjectRule($ruleValue);

        if (!$shouldMatch) {
            $this->expectException(Mismatches\MismatchCollection::class);
            $this->expectExceptionMessage('properties not matching');
        }

        $rule->assertMatch($testValue);

        self::assertTrue(true, 'No exceptions should be thrown if matching');
    }
}
