<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\HasPropertyExamples;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class TestObject implements JsonSchemaAwareRecord, HasPropertyExamples
{
    use JsonSchemaAwareRecordLogic;

    /** Testdoc */
    private string $test;
    private TestStringVO $testString;
    /** @var array<string> */
    private array $list;
    private string|null $nullable = null;
    private int $normalValue = 5;
    private TestIntVO $valueDefaultViaFunction;
    /** @example {"test": "test"} */
    private TestSubObject|null $subObject = null;
    /** @var array<TestSubObject> */
    private array $subObjects = [];

    public function normalValue(): int
    {
        return $this->normalValue;
    }

    public function valueDefaultViaFunction(): TestIntVO
    {
        return $this->valueDefaultViaFunction;
    }

    /** @return array<string, string> */
    private static function arrayPropItemTypeMap(): array
    {
        return [
            'list' => 'string',
            'subObjects' => TestSubObject::class,
        ];
    }

    /** @return array<string, mixed> */
    private static function maxValues(): array
    {
        return ['normalValue' => 10];
    }

    /** @return array<string, mixed> */
    private static function __defaultProperties(): array
    {
        return [
            'valueDefaultViaFunction' => TestIntVO::fromInt(0),
        ];
    }

    /** @return array<string, mixed> */
    public static function examples(): array
    {
        return [
            'test' => TestStringVO::fromString('test'),
        ];
    }

    /**
     * Because it is a private static property, we can't access it in our test.
     *
     * @return array<mixed>|null
     */
    public static function getPropTypeMap(): array|null
    {
        return self::$__propTypeMap;
    }

    /**
     * This function is used to mimic a clean test object that has never been instantiated.
     */
    public static function resetPropTypeMap(): void
    {
        self::$__propTypeMap = null;
    }
}
