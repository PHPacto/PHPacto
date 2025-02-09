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

namespace PHPacto\Factory;

use PHPacto\Encoder\BodyEncoder;
use PHPacto\Encoder\HeadersEncoder;
use PHPacto\Matcher\Rules\EqualsRule;
use PHPacto\Matcher\Rules\StringRule;
use Psr\Http\Message\MessageInterface;

abstract class PactMessageFactory
{
    protected static function getHeadersRules(MessageInterface $response)
    {
        $decodedHeaders = HeadersEncoder::decode($response->getHeaders());

        return self::getHeaderRulesFromArray($decodedHeaders);
    }

    protected static function getBodyRules(MessageInterface $response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $decodedBody = BodyEncoder::decode((string) $response->getBody(), $contentType);

        return !empty($decodedBody) ? new EqualsRule($decodedBody) : null;
    }

    protected static function getHeaderRulesFromArray(array $headers): array
    {
        $map = function ($value) {
            if (\is_array($value)) {
                return self::getHeaderRulesFromArray($value);
            }

            return new StringRule($value);
        };

        return array_map($map, $headers);
    }
}
