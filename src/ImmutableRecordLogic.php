<?php

//phpcs:disable Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps


declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\SpecialKeySupport;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use TeamBlue\JsonImmutableObjects\Attribute\SensitiveData;
use TeamBlue\JsonImmutableObjects\Encryption\EncryptDecryptService;
use TeamBlue\Util\ArrayUtil;
use TeamBlue\Util\ValueObjectUtil;
use TeamBlue\ValueObjects\SensitiveValue;

use function array_filter;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_push;
use function assert;
use function class_exists;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function method_exists;
use function sprintf;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;
use const JSON_THROW_ON_ERROR;

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
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
    private static bool $__useMaxValues = false;

    /** Ensure the $__propTypeMap has been set after deserialization */
    public function __wakeup(): void
    {
        self::initPropTypeMap();
    }

    protected static function initPropTypeMap(): void
    {
        if (self::$__propTypeMap !== null) {
            return;
        }

        self::$__propTypeMap = self::buildPropTypeMap();
    }

    private function init(): void
    {
        $this->immutableRecordInit();
        $this->addDefaultProperties();
        $this->useRecursiveMaxValues();

        self::$__useMaxValues = false;
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

    public function useRecursiveMaxValues(): void
    {
        // phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps
        foreach (self::$__propTypeMap as $key => [$type, $isNative, $isNullable]) {
            if ($isNative || isset($this->{$key})) {
                continue;
            }

            try {
                $reflectionType = new ReflectionClass($type);
            } catch (ReflectionException) {
                continue;
            }

            if (! $reflectionType->implementsInterface(ImmutableRecord::class)) {
                continue;
            }

            try {
                $this->{$key} = self::$__useMaxValues && method_exists($type, 'fromArrayWithDefaultMaxValues')
                    ? $type::fromArrayWithDefaultMaxValues([])
                    : $type::fromArray([]);
            } catch (InvalidArgumentException $exception) {
                if ($isNullable) {
                    $this->{$key} = null;
                } elseif (! str_starts_with($exception->getMessage(), 'Missing record data for key')) {
                    throw $exception;
                }
            }
        }
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
        unset($propTypeMap['__useMaxValues']);

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

    /**
     * @param array<mixed> $nativeData
     *
     * @return self
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public static function fromArrayWithDefaultMaxValues(array $nativeData)
    {
        self::$__useMaxValues = true;
        $nativeData = array_merge(self::maxValues(), $nativeData);

        return self::fromArray($nativeData);
    }

    /** @return array<string, mixed> */
    private static function maxValues(): array
    {
        return [];
    }

    public static function create(): self
    {
        return self::fromArray([]);
    }

    public static function fromJson(string $json): self
    {
        $nativeData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($nativeData)) {
            throw new RuntimeException('Invalid JSON data.');
        }

        return self::fromArray($nativeData);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromYaml(string $yaml): self
    {
        $nativeData = Yaml::parse($yaml);

        if (! is_array($nativeData)) {
            throw new RuntimeException('Invalid Yaml data.');
        }

        return self::fromArray($nativeData);
    }

    public function toYaml(): string
    {
        return Yaml::dump($this->toArray());
    }

    /** @return array<mixed> */
    private static function allExamples(): array
    {
        if (
            (new ReflectionClass(static::class))->implementsInterface(HasPropertyExamples::class)
            && method_exists(static::class, 'examples')
        ) {
            return static::examples();
        }

        return [];
    }

    /**
     * Combine the __defaultProperties method with the php properties that have a default value.
     *
     * @return array<string, mixed>
     */
    public static function defaultProperties(): array
    {
        self::initPropTypeMap();

        $propertyNames     = array_keys(self::$__propTypeMap);
        $defaultProperties = self::__defaultProperties();

        return array_filter(
            [...(new ReflectionClass(static::class))->getDefaultProperties(), ...$defaultProperties],
            static fn ($key) => in_array($key, $propertyNames),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
