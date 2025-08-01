<?php

//phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps


declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\SpecialKeySupport;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use TeamBlue\JsonImmutableObjects\Attribute\SensitiveData;
use TeamBlue\JsonImmutableObjects\Encryption\EncryptDecryptService;
use TeamBlue\Util\ArrayUtil;
use TeamBlue\Util\ValueObjectUtil;
use TeamBlue\ValueObjects\SensitiveValue;

use function array_intersect_key;
use function array_key_exists;
use function array_map;
use function array_push;
use function assert;
use function class_exists;
use function is_array;
use function is_string;
use function method_exists;
use function sprintf;

/** @SuppressWarnings(PHPMD) */
trait ImmutableRecordLogic
{
    use \EventEngine\Data\ImmutableRecordLogic {
        fromArray as parentFromArray;
        setNativeData as baseSetNativeData;
        setRecordData as baseSetRecordData;
        voTypeToNative as parentVoTypeToNative;
        fromType as parentFromType;
        buildPropTypeMap as parentBuildPropTypeMap;
        init as immutableRecordInit;
    }

    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
    private static bool $__encryptedSensitiveData = false;

    /** Ensure the $__propTypeMap has been set after deserialization */
    public function __wakeup(): void
    {
        self::initPropTypeMap();
    }

    private function init(): void
    {
        $this->immutableRecordInit();

        $this->addDefaultProperties();
    }

    protected static function initPropTypeMap(): void
    {
        if (self::$__propTypeMap !== null) {
            return;
        }

        self::$__propTypeMap = self::buildPropTypeMap();
    }

    /**
     * Unset __encryptedSensitiveData and add extra bool to prop type map for sensitive parameters.
     *
     * @return array<mixed>
     */
    private static function buildPropTypeMap(): array
    {
        $propTypeMap = self::parentBuildPropTypeMap();
        $reflectionClass = new ReflectionClass(self::class);

        foreach ($propTypeMap as $key => &$value) {
            $reflectionProperty = $reflectionClass->getProperty($key);
            array_push($value, self::isPropertySensitive($reflectionProperty));
        }

        unset($propTypeMap['__encryptedSensitiveData']);

        return $propTypeMap;
    }

    private static function isPropertySensitive(ReflectionProperty $reflectionProperty): bool
    {
        $sensitiveDataAttributes = $reflectionProperty->getAttributes(SensitiveData::class);

        if (! empty($sensitiveDataAttributes)) {
            return true;
        }

        $type = $reflectionProperty->getType();

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        $typeClass = $type->getName();

        return class_exists($typeClass)
            && (new ReflectionClass($typeClass))->implementsInterface(SensitiveValue::class);
    }

    /**
     * Convert value objects to native data
     * Filter out the not allowed properties.
     * Convert the keys to camel case.
     *
     * Decrypt sensitive data before setting the native data.
     *
     * @param array<string, mixed> $nativeData
     */
    private function setNativeData(array $nativeData): void
    {
        assert(is_array(self::$__propTypeMap));

        $nativeData = array_map(
            static fn ($value) => ValueObjectUtil::toScalar($value) ?? $value,
            $nativeData,
        );

        // Filter not allowed properties.
        $filteredNativeData = array_intersect_key(
            $this->convertKeys($nativeData),
            self::$__propTypeMap,
        );

        if (! self::$__encryptedSensitiveData) {
            $this->baseSetNativeData($filteredNativeData);

            return;
        }

        $recordData = [];
        $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        foreach ($filteredNativeData as $key => $val) {
            $specialKey = $key;

            if ($this instanceof SpecialKeySupport) {
                $specialKey = $this->convertKeyForRecord($key);
            }

            if (! isset(self::$__propTypeMap[$specialKey])) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid property passed to Record %s. Got property with key ' . $specialKey,
                    static::class,
                ));
            }

            [$type, $isNative, $isNullable, $isSensitive] = self::$__propTypeMap[$specialKey];

            if ($val === null) {
                if (! $isNullable) {
                    throw new RuntimeException(
                        sprintf(
                            'Got null for non nullable property %s of Record %s',
                            $specialKey,
                            static::class,
                        ),
                    );
                }

                $recordData[$key] = null;
                continue;
            }

            if (is_string($val) && $isSensitive) {
                $val = EncryptDecryptService::decrypt($val);
            }

            switch ($type) {
                case ImmutableRecord::PHP_TYPE_STRING:
                case ImmutableRecord::PHP_TYPE_INT:
                case ImmutableRecord::PHP_TYPE_FLOAT:
                case ImmutableRecord::PHP_TYPE_BOOL:
                    $recordData[$key] = $val;
                    break;
                case ImmutableRecord::PHP_TYPE_ARRAY:
                    if (
                        array_key_exists($specialKey, $arrayPropItemTypeMap)
                        && ! self::isScalarType($arrayPropItemTypeMap[$specialKey])
                    ) {
                        $recordData[$key] = array_map(function ($item) use ($specialKey, &$arrayPropItemTypeMap) {
                            return $this->fromType($item, $arrayPropItemTypeMap[$specialKey]);
                        }, $val);
                    } else {
                        $recordData[$key] = $val;
                    }

                    break;
                default:
                    $recordData[$key] = $this->fromType($val, $type);
            }
        }

        $this->setRecordData($recordData);
    }

    /**
     * Filter out the not allowed properties
     * Convert the keys to camel case.
     *
     * @param array<string, mixed> $recordData
     */
    private function setRecordData(array $recordData): void
    {
        assert(is_array(self::$__propTypeMap));

        $filteredRecordData = array_intersect_key(
            $this->convertKeys($recordData),
            self::$__propTypeMap,
        );

        $this->baseSetRecordData($filteredRecordData);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertKeys(array $data): array
    {
        /** @var array<string, mixed> $data */
        $data = ArrayUtil::toCamelCasedKeys($data); // todo remove and use SpecialKeySupport

        if (! $this instanceof SpecialKeySupport) {
            return $data;
        }

        $dataWithConvertedKeys = [];
        foreach ($data as $key => $value) {
            $dataWithConvertedKeys[$this->convertKeyForRecord($key)] = $value;
        }

        return $dataWithConvertedKeys;
    }

    private function addDefaultProperties(): void
    {
        $defaultProperties = self::__defaultProperties();

        foreach ($defaultProperties as $property => $defaultValue) {
            if (! is_string($property)) {
                throw new LogicException(
                    sprintf(
                        'The __defaultProperties method from \'%s\', should be an associative array' .
                        'where the key is the property and the value is the default value. Found key \'%d\'.',
                        static::class,
                        $property,
                    ),
                );
            }

            if (isset($this->{$property})) {
                continue;
            }

            $this->{$property} = $defaultValue;
        }
    }

    /** @return array<string, mixed> */
    public static function __defaultProperties(): array
    {
        return [];
    }

    private function fromType(mixed $value, string $type): mixed
    {
        if (is_array($value) && class_exists($type)) {
            return match (true) {
                self::$__encryptedSensitiveData => $type::fromEncryptedSensitiveData($value),
                self::$__useMaxValues => $type::fromArrayWithDefaultMaxValues($value),
                default => $this->parentFromType($value, $type),
            };
        }

        return $this->parentFromType($value, $type);
    }

    /**
     * Overwritten to convert value objects to native data
     * before sending them to the parent fromArray method.
     *
     * @param array<mixed> $nativeData
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public static function fromEncryptedSensitiveData(array $nativeData)
    {
        self::$__encryptedSensitiveData = true;

        $immutable = self::parentFromArray($nativeData);

        self::$__encryptedSensitiveData = false;

        return $immutable;
    }

    /** @return array<string, mixed> */
    public function toSensitiveEncryptedArray(): array
    {
        self::$__encryptedSensitiveData = true;

        $nativeData = [];
        $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        foreach (self::$__propTypeMap as $key => [$type, $isNative, $isNullable, $isSensitive]) {
            $specialKey = $key;

            if ($this instanceof SpecialKeySupport) {
                $specialKey = $this->convertKeyForArray($key);
            }

            switch ($type) {
                case ImmutableRecord::PHP_TYPE_STRING:
                case ImmutableRecord::PHP_TYPE_INT:
                case ImmutableRecord::PHP_TYPE_FLOAT:
                case ImmutableRecord::PHP_TYPE_BOOL:
                case ImmutableRecord::PHP_TYPE_ARRAY:
                    if (
                        array_key_exists($key, $arrayPropItemTypeMap)
                        && ! self::isScalarType($arrayPropItemTypeMap[$key])
                    ) {
                        if ($isNullable && $this->{$key} === null) {
                            $nativeData[$specialKey] = null;
                            continue 2;
                        }

                        $nativeData[$specialKey] = array_map(function ($item) use ($key, &$arrayPropItemTypeMap) {
                            return $this->voTypeToNative($item, $key, $arrayPropItemTypeMap[$key]);
                        }, $this->{$key});
                    } else {
                        $nativeData[$specialKey] = $this->{$key};
                    }

                    break;
                default:
                    if ($isNullable && (! isset($this->{$key}))) {
                        $nativeData[$specialKey] = null;
                        continue 2;
                    }

                    $nativeData[$specialKey] = $this->voTypeToNative($this->{$key}, $key, $type);
            }

            if (! $isSensitive) {
                continue;
            }

            if (! is_string($nativeData[$specialKey])) {
                throw new RuntimeException(
                    sprintf('Sensitive data must be a string. Property \'%s\' is not a string.', $key),
                );
            }

            $nativeData[$specialKey] = EncryptDecryptService::encrypt($nativeData[$specialKey]);
        }

        self::$__encryptedSensitiveData = false;

        return $nativeData;
    }

    private function voTypeToNative(mixed $value, string $key, string $type): mixed
    {
        if (self::$__encryptedSensitiveData && method_exists($value, 'toSensitiveEncryptedArray')) {
            return $value->toSensitiveEncryptedArray();
        }

        return $this->parentVoTypeToNative($value, $key, $type);
    }
}
