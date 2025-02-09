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

namespace PHPacto\Serializer;

use PHPacto\Matcher\Mismatches;
use PHPacto\Matcher\Rules;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Webmozart\Assert\Assert;

class RuleNormalizer extends AbstractNormalizer
{
    public function __construct(
        private RuleMap $ruleMap,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = [],
        ?PropertyInfoExtractorInterface $propertyInfoExtractor = null
    ) {
        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyAccessor,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext,
            $propertyInfoExtractor
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Rules\Rule::class => true,
            Rules\Rule::class.'[]' => true,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return \is_object($data) && self::isRule(\get_class($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return self::isRule($type) && (null === $data || \is_array($data) || is_scalar($data));
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        Assert::isInstanceOf($object, Rules\Rule::class);

        if (Rules\StringRule::class === \get_class($object) || $object instanceof Rules\NumericRule || $object instanceof Rules\BooleanRule) {
            return $this->serializer->normalize($object->getSample(), $format, $this->createChildContext($context, 'sample', $format));
        }

        if ($object instanceof Rules\ObjectRule && !$object->hasSample()) {
            return $this->serializer->normalize($object->getProperties(), $format, $this->createChildContext($context, 'properties', $format));
        }

        return $this->normalizeRuleObject($object, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $type = rtrim($type, '[]');

        if (!(
            Rules\Rule::class === $type
            || is_subclass_of($type, Rules\Rule::class)
            || class_implements($type)[Rules\Rule::class] ?? false
        )) {
            throw new InvalidArgumentException(sprintf('The class "%s" should extend "%s"', $type, Rules\Rule::class));
        }

        if (\is_array($data)) {
            if (\array_key_exists('_rule', $data)) {
                $class = $this->ruleMap->getClassName($data['_rule']);
                unset($data['_rule']);

                return $this->denormalizeRuleArray($data, $class, $format, $context);
            }

            $mismatches = [];

            foreach ($data as $key => $value) {
                try {
                    $data[$key] = $this->serializer->denormalize($data[$key], $type, $format, $this->createChildContext($context, $key, $format));
                } catch (Mismatches\Mismatch $e) {
                    $mismatches[$key] = $e;
                }
            }

            if ($mismatches) {
                throw new Mismatches\MismatchCollection($mismatches, 'There are {{ count }} errors');
            }

            if (($context['parent'] ?? null) !== Rules\ObjectRule::class && \count($data) && self::isArrayAssociative($data)) {
                return new Rules\ObjectRule($data);
            }

            return $data;
        }

        if (\is_bool($data)) {
            return new Rules\BooleanRule($data);
        }

        if (\is_string($data)) {
            return new Rules\StringRule($data);
        }

        if (is_numeric($data)) {
            return new Rules\NumericRule($data);
        }

        return new Rules\EqualsRule($data);
    }

    /**
     * {@inheritdoc}
     */
    protected function isAllowedAttribute(object|string $object, string $attribute, ?string $format = null, array $context = []): bool
    {
        if (\is_object($object) && 'sample' === $attribute && method_exists($object, 'getValue')) {
            if ($object->getValue() === $object->getSample()) {
                return false;
            }
        }

        if (\is_object($object) && Rules\ObjectRule::class === \get_class($object) && 'rules' === $attribute) {
            return false;
        }

        return parent::isAllowedAttribute($object, $attribute, $format, $context);
    }

    /*
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        $constructor = $this->getConstructor($data, $class, $context, $reflectionClass, $allowedAttributes);
        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $mismatches = [];
            $params = [];
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = $constructorParameter->name;
                $key = $this->nameConverter ? $this->nameConverter->normalize($paramName) : $paramName;

                $allowed = false === $allowedAttributes || \in_array($paramName, $allowedAttributes, true);
                $ignored = !$this->isAllowedAttribute($class, $paramName, $format, $context);
                if ($constructorParameter->isVariadic()) {
                    if ($allowed && !$ignored && (isset($data[$key]) || \array_key_exists($key, $data))) {
                        if (!\is_array($data[$paramName])) {
                            throw new RuntimeException(sprintf('Cannot create an instance of %s from serialized data because the variadic parameter %s can only accept an array.', $class, $constructorParameter->name));
                        }

                        $params = array_merge($params, $data[$paramName]);
                    }
                } elseif ($allowed && !$ignored && (isset($data[$key]) || \array_key_exists($key, $data))) {
                    $parameterData = $data[$key];
                    if (null === $parameterData && $constructorParameter->allowsNull()) {
                        $params[] = null;
                        // Don't run set for a parameter passed to the constructor
                        unset($data[$key]);
                        continue;
                    }
                    try {
                        $parameterType = self::getParameterReflectionClass($constructorParameter);

                        if (null !== $parameterType) {
                            $parameterData = $this->recursiveDenormalization($parameterData, $parameterType->getName(), $format, $this->createChildContext($context, $paramName, $format));
                        }
                    } catch (Mismatches\Mismatch $e) {
                        $mismatches[strtoupper($key)] = $e;
                    } catch (\ReflectionException $e) {
                        throw new RuntimeException(sprintf('Could not determine the class of the parameter "%s".', $key), 0, $e);
                    } catch (MissingConstructorArgumentsException $e) {
                        if (!$constructorParameter->getType()->allowsNull()) {
                            throw $e;
                        }
                        $parameterData = null;
                    }

                    // Don't run set for a parameter passed to the constructor
                    $params[] = $parameterData;
                    unset($data[$key]);
                } elseif ($constructorParameter->isDefaultValueAvailable()) {
                    $params[] = $constructorParameter->getDefaultValue();
                } else {
                    $message = sprintf('Cannot create an instance of %s from serialized data because its constructor requires parameter "%s" to be present.', $class, $constructorParameter->name);

                    // MissingConstructorArgumentsException added on Sf 4.1
                    if (class_exists(MissingConstructorArgumentsException::class)) {
                        throw new MissingConstructorArgumentsException($message);
                    }

                    throw new RuntimeException($message);
                }
            }

            if ($mismatches) {
                throw new Mismatches\MismatchCollection($mismatches, 'There are {{ count }} errors');
            }

            if ($constructor->isConstructor()) {
                return $reflectionClass->newInstanceArgs($params);
            }

            return $constructor->invokeArgs(null, $params);
        }

        return new $class();
    }*/

    /**
     * {@inheritdoc}
     */
    private function normalizeRuleObject(Rules\Rule $rule, $format = null, array $context = [])
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = false;
        }

        $data = [
            '_rule' => $this->ruleMap->getAlias(\get_class($rule)),
        ];

        $attributes = $this->getAttributes($rule, $format, $context);

        foreach ($attributes as $attribute) {
            if ($attribute === 'sample' && !$rule->hasSample()) {
                continue;
            }

            $attributeValue = $this->getAttributeValue($rule, $attribute, $format, $context);

            if (null === $attributeValue || (is_array($attributeValue) && 0 === count($attributeValue))) {
                continue;
            }

            if ($this->nameConverter) {
                $attribute = $this->nameConverter->normalize($attribute);
            }

            if (is_scalar($attributeValue)) {
                $data[$attribute] = $attributeValue;
            } else {
                $data[$attribute] = $this->serializer->normalize($attributeValue, $format, $this->createChildContext($context, $attribute, $format));
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    private function denormalizeRuleArray($data, $class, $format = null, array $context = []): Rules\Rule
    {
        if (\array_key_exists('rules', $data) && \is_array($data['rules'])) {
            $data['rules'] = $this->serializer->denormalize($data['rules'], Rules\Rule::class . '[]', $format, $this->createChildContext($context, 'rules', $format));
        } elseif (Rules\ObjectRule::class === $class && \array_key_exists('properties', $data) && \is_array($data['properties'])) {
            $data['properties'] = $this->serializer->denormalize($data['properties'], Rules\Rule::class . '[]', $format, ['parent' => $class] + $this->createChildContext($context, 'properties', $format));
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

    private static function isRule(string $class): bool
    {
        $class = rtrim($class, '[]');

        return Rules\Rule::class === $class || is_subclass_of($class, Rules\Rule::class);
    }

    private static function isArrayAssociative(array $array): bool
    {
        return array_values($array) !== $array;
    }
}
