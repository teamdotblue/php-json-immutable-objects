<?php

//phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps


declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects;

use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type;
use EventEngine\JsonSchema\Type\ObjectType;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use TeamBlue\Util\ScalarUtil;
use TeamBlue\Util\ValueObjectUtil;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function count;
use function sprintf;

trait JsonSchemaAwareRecordLogic
{
    use \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
    use ImmutableRecordLogic {
        ImmutableRecordLogic::fromType insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
        ImmutableRecordLogic::voTypeToNative insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
        ImmutableRecordLogic::setNativeData insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
        ImmutableRecordLogic::setRecordData insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
        ImmutableRecordLogic::init insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
        ImmutableRecordLogic::buildPropTypeMap insteadof \EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;

    }

    /** @param array<mixed> $arrayPropTypeMap */
    private static function generateSchemaFromPropTypeMap(array $arrayPropTypeMap = []): Type\ObjectType
    {
        self::initPropTypeMap();

        //To keep BC, we cache arrayPropTypeMap internally.
        //New recommended way to provide the map is that
        //one should override the static method self::arrayPropItemTypeMap()
        //Hence, we check if this method returns a non empty array and only in this case cache the map
        if (count($arrayPropTypeMap) && ! count(self::arrayPropItemTypeMap())) {
            self::$__arrayPropItemTypeMap = $arrayPropTypeMap;
        }

        if (self::$__schema === null) {
            $properties        = [];
            $defaultProperties = self::defaultProperties();
            $examplesPerProperty = self::allExamples();

            foreach (self::$__propTypeMap as $propertyName => [$type, $isScalar, $isNullable, $isSensitive]) {
                $properties[$propertyName] = $property = self::baseProperty(
                    $propertyName,
                    $type,
                    $isScalar,
                    $isNullable,
                    $isSensitive,
                );

                if (! $property instanceof AnnotatedType) {
                    continue;
                }

                $reflectionProperty = (new ReflectionClass(static::class))->getProperty($propertyName);
                $property = self::addPropertyDescription($property, $reflectionProperty);
                $property = self::addPropertyExamples($property, $reflectionProperty, $examplesPerProperty);
                $property = self::addPropertyDefaults($property, $defaultProperties, $propertyName);

                $properties[$propertyName] = $property;
            }

            $optionalProperties = array_flip(self::__optionalProperties());
            $propertiesWithoutOptional = array_diff_key($properties, $optionalProperties);
            $optionalProperties = array_intersect_key($properties, $optionalProperties);

            self::$__schema = JsonSchema::object($propertiesWithoutOptional, $optionalProperties);
        }

        return self::$__schema;
    }

    private static function baseProperty(
        string $propertyName,
        string $type,
        bool $isScalar,
        bool $isNullable,
        bool $isSensitive,
    ): Type {
        if ($isScalar) {
            return JsonSchema::schemaFromScalarPhpType($type, $isNullable);
        }

        $schema = $type === ImmutableRecord::PHP_TYPE_ARRAY
            ? self::schemaFromArrayProperty($propertyName)
            : self::getTypeFromClass($type);

        if (! $isNullable) {
            return $schema;
        }

        return JsonSchema::nullOr($schema);
    }

    private static function schemaFromArrayProperty(string $propertyName): Type
    {
        $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();
        if (! array_key_exists($propertyName, $arrayPropItemTypeMap)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Missing array item type in array property map. ' .
                    'Please provide an array item type for property %s.',
                    $propertyName,
                ),
            );
        }

        $arrayItemType = $arrayPropItemTypeMap[$propertyName];

        if (self::isScalarType($arrayItemType)) {
            return JsonSchema::array(JsonSchema::schemaFromScalarPhpType($arrayItemType, false));
        }

        if ($arrayItemType === ImmutableRecord::PHP_TYPE_ARRAY) {
            throw new InvalidArgumentException(
                sprintf(
                    "Array item type of property %s must not be 'array', " .
                    'only a scalar type or an existing class can be used as array item type.',
                    $propertyName,
                ),
            );
        }

        return JsonSchema::array(self::getTypeFromClass($arrayItemType));
    }

    private static function addPropertyDescription(
        AnnotatedType $property,
        ReflectionProperty $reflectionProperty,
    ): AnnotatedType {
        $description = DocBlockFactory::summaryAndDescription($reflectionProperty);

        return $description
            ? $property->describedAs($description)
            : $property;
    }

    /** @param array<string, mixed> $examplesPerProperty */
    private static function addPropertyExamples(
        AnnotatedType $property,
        ReflectionProperty $reflectionProperty,
        array $examplesPerProperty,
    ): AnnotatedType {
        $propertyExamples = [];

        $propertyName = $reflectionProperty->getName();
        if ($examplesPerProperty[$propertyName] ?? false) {
            $example = $examplesPerProperty[$propertyName];
            $propertyExamples[] = ValueObjectUtil::toScalar($example) ?? $example;
        }

        $propertyExamples = [...$propertyExamples, ...DocBlockFactory::examples($reflectionProperty)];

        if (! empty($propertyExamples)) {
            $property = $property->withExamples(...$propertyExamples);
        }

        return $property;
    }

    /** @param array<string, mixed> $defaultProperties */
    private static function addPropertyDefaults(
        AnnotatedType $property,
        array $defaultProperties,
        string $propertyName,
    ): AnnotatedType {
        if (! isset($defaultProperties[$propertyName])) {
            return $property;
        }

        return $property->withDefault(ScalarUtil::toScalar($defaultProperties[$propertyName]));
    }

    private static function getTypeFromClass(string $classOrType): Type
    {
        return self::__allowNestedSchema()
            ? TypeDetector::typeFromClass($classOrType)
            : TypeDetector::typeFromClassAsReference($classOrType);
    }

    private static function __allowNestedSchema(): bool
    {
        return true;
    }

    /**
     * @return array<string>
     *
     * @inheritDoc
     */
    private static function __optionalProperties(): array
    {
        return array_keys(self::defaultProperties());
    }

    /**
     * @var ObjectType
     * phpcs:disable
     */
    private static $__schema;
}
