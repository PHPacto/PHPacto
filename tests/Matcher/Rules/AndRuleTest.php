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

class AndRuleTest extends SerializerAwareTestCase
{
    public function test_it_is_normalizable()
    {
        $childRule = $this->rule->empty();
        $rule = new AndRule([$childRule, $childRule]);

        $expected = [
            '_rule' => 'and',
            'rules' => [
                ['_rule' => \get_class($childRule)],
                ['_rule' => \get_class($childRule)],
            ],
        ];

        self::assertEquals($expected, $this->normalizer->normalize($rule));
    }

    public function test_it_is_denormalizable()
    {
        $childRule = $this->rule->empty();

        $data = [
            '_rule' => 'and',
            'rules' => [
                ['_rule' => \get_class($childRule)],
                ['_rule' => \get_class($childRule)],
            ],
            'sample' => 5,
        ];

        $rule = $this->normalizer->denormalize($data, Rule::class);

        self::assertInstanceOf(AndRule::class, $rule);
        self::assertSame(5, $rule->getSample());
        self::assertCount(2, $rule->getRules());
        self::assertInstanceOf(Rule::class, $rule->getRules()[0]);
        self::assertInstanceOf(Rule::class, $rule->getRules()[1]);
    }

    public function supportedValuesProvider()
    {
        $this->setUp();
        $rule = $this->rule->empty();

        return [
            [false, []],
            [false, 100],
            [false, .1],
            [false, 'string'],
            [false, true],
            [false, false],
            [false, null],
            [false, new \stdClass()],
            [false, $rule],
            [false, [[]]],
            [false, [100]],
            [false, [.1]],
            [false, ['string']],
            [false, [true]],
            [false, [false]],
            [false, [null]],
            [false, [new \stdClass()]],
            [true, [$rule]],
            [true, ['key' => $rule]],
        ];
    }

    /**
     * @dataProvider supportedValuesProvider
     *
     * @param mixed $value
     */
    public function testSupportedValues(bool $shouldBeSupported, $value)
    {
        $rule = self::getMockBuilder(AndRule::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['assertSupport'])
            ->getMock();

        if (!$shouldBeSupported) {
            $this->expectException(\Throwable::class);
        }

        $method = new \ReflectionMethod(AndRule::class, 'assertSupport');
        $method->setAccessible(true);
        $method->invoke($rule, $value);

        self::assertTrue(true, 'No exceptions should be thrown');
    }

    public function testMatch()
    {
        $mock = self::getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->getMock();

        new AndRule([$mock], 'No Mismatch is thrown');

        self::assertTrue(true, 'No exceptions should be thrown');
    }

    public function testMismatch()
    {
        $mockOk = self::getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockMismatch = self::getMockBuilder(Rule::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockMismatch
            ->method('assertMatch')
            ->willThrowException(new Mismatches\ValueMismatch('A mismatch is expected', true, false));

        $this->expectException(Mismatches\MismatchCollection::class);
        $this->expectExceptionMessage('rules not matching the value');

        new AndRule([$mockOk, $mockMismatch, $mockOk, $mockMismatch, $mockOk], 'A Mismatch should be thrown if matching');
    }

    /**
     * @depends testMismatch
     */
    public function testMismatchCount()
    {
        try {
            $this->testMismatch();
        } catch (Mismatches\MismatchCollection $e) {
            self::assertCount(2, $e);

            throw $e;
        }

        self::fail('This test should end in the catch');
    }
}
