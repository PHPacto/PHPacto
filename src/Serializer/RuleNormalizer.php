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

namespace Bigfoot\PHPacto\Serializer;

use Bigfoot\PHPacto\Matcher\Rules\BooleanRule;
use Bigfoot\PHPacto\Matcher\Rules\EqualsRule;
use Bigfoot\PHPacto\Matcher\Rules\ObjectRule;
use Bigfoot\PHPacto\Matcher\Rules\Rule;
use Bigfoot\PHPacto\Matcher\Rules\StringEqualsRule;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RuleNormalizer extends GetSetMethodNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @var RuleMap
     */
    private $ruleMap;

    public function __construct(RuleMap $ruleMap, ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null)
    {
        parent::__construct($classMetadataFactory, $nameConverter);

        $this->ruleMap = $ruleMap;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && self::isRule(\get_class($data)) && self::isFormatSupported($format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return self::isRule($type) && self::isFormatSupported($format) && (null === $data || \is_array($data) || is_scalar($data));
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (!$object instanceof Rule) {
            throw new InvalidArgumentException(sprintf('The object "%s" must implement "%s".', \get_class($object), Rule::class));
        }

        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        if ($object instanceof BooleanRule || $object instanceof EqualsRule || ($object instanceof StringEqualsRule && $object->isCaseSensitive() && $object->getValue() === $object->getSample())) {
            return $this->recursiveNormalization($object->getSample(), $format, $this->createChildContext($context, 'sample'));
        }

        if ($object instanceof ObjectRule && null === $object->getSample()) {
            return $this->recursiveNormalization($object->getProperties(), $format, $this->createChildContext($context, 'properties'));
        }

        return $this->normalizeRuleObject($object, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $class = rtrim($class, '[]');

        if (!(Rule::class === $class || (interface_exists($class) && is_subclass_of($class, Rule::class)))) {
            throw new InvalidArgumentException(sprintf('Interface "%s" should extends "%s"', $class, Rule::class));
        }

        if (\is_array($data)) {
            if (\array_key_exists('@rule', $data)) {
                $class = $this->ruleMap->getClassName($data['@rule']);
                unset($data['@rule']);

                return $this->denormalizeRuleArray($data, $class, $format, $context);
            }

            foreach ($data as $key => $value) {
                $data[$key] = $this->recursiveDenormalization($data[$key], $class, $format, $this->createChildContext($context, $key));
            }

            if (($context['parent'] ?? null) !== ObjectRule::class && \count($data) && $this->isArrayAssociative($data)) {
                return new ObjectRule($data);
            }

            return $data;
        }

        if (\is_bool($data)) {
            return new BooleanRule($data);
        }

        if (\is_string($data) && '' !== $data) {
            return new StringEqualsRule($data, true);
        }

        return new EqualsRule($data);
    }

    protected function isAllowedAttribute($object, $attribute, $format = null, array $context = [])
    {
        if (\is_object($object) && 'sample' === $attribute && method_exists($object, 'getValue')) {
            if ($object->getValue() === $object->getSample()) {
                return false;
            }
        }

        if (\is_object($object) && ObjectRule::class === \get_class($object) && 'rules' === $attribute) {
            return false;
        }

        return parent::isAllowedAttribute($object, $attribute, $format, $context);
    }

    private static function isRule(string $class): bool
    {
        $class = rtrim($class, '[]');

        return Rule::class === $class || is_subclass_of($class, Rule::class);
    }

    private static function isFormatSupported(?string $format): bool
    {
        return \in_array($format, [null, 'json', 'yaml'], true);
    }

    private function normalizeRuleObject(Rule $rule, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $data = [
            '@rule' => $this->ruleMap->getAlias(\get_class($rule)),
        ];

        $attributes = $this->getAttributes($rule, $format, $context);

        foreach ($attributes as $attribute) {
            $attributeValue = $this->getAttributeValue($rule, $attribute, $format, $context);

            if (null === $attributeValue) {
                continue;
            }

            if ($this->nameConverter) {
                $attribute = $this->nameConverter->normalize($attribute);
            }

            if (is_scalar($attributeValue)) {
                $data[$attribute] = $attributeValue;
            } else {
                $data[$attribute] = $this->recursiveNormalization($attributeValue, $format, $this->createChildContext($context, $attribute));
            }
        }

        return $data;
    }

    private function denormalizeRuleArray($data, $class, $format = null, array $context = []): Rule
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        if (\array_key_exists('rules', $data) && \is_array($data['rules'])) {
            $data['rules'] = $this->recursiveDenormalization($data['rules'], Rule::class . '[]', $format, $this->createChildContext($context, 'rules'));
        } elseif (ObjectRule::class === $class && \array_key_exists('properties', $data) && \is_array($data['properties'])) {
            $data['properties'] = $this->recursiveDenormalization($data['properties'], Rule::class . '[]', $format, ['parent' => $class] + $this->createChildContext($context, 'properties'));
        }

        $allowedAttributes = $this->getAllowedAttributes($class, $context, true);

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);

        foreach ($data as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute);
            }

            if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes, true)) || !$this->isAllowedAttribute($class, $attribute, $format, $context)) {
                $extraAttributes[] = $attribute;

                continue;
            }

            try {
                $this->setAttributeValue($object, $attribute, $value, $format, $context);
            } catch (InvalidArgumentException $e) {
                throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!empty($extraAttributes)) {
            throw new ExtraAttributesException($extraAttributes);
        }

        return $object;
    }

    private function recursiveNormalization($data, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new LogicException('Cannot normalize data because the injected serializer is not a normalizer');
        }

        return $this->serializer->normalize($data, $format, $context);
    }

    private function recursiveDenormalization($data, $class, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new LogicException('Cannot denormalize data because the injected serializer is not a normalizer');
        }

        return $this->serializer->denormalize($data, $class, $format, $context);
    }

    private function isArrayAssociative(array $array): bool
    {
        return array_values($array) !== $array;
    }

    /**
     * Gets the cache key to use.
     *
     * @param string|null $format
     * @param array       $context
     *
     * @return bool|string
     */
    private function getCacheKey($format, array $context)
    {
        try {
            return md5($format . serialize($context));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
