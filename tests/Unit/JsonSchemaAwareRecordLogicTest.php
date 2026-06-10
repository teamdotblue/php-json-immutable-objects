<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TeamBlue\JsonImmutableObjects\Tests\Object\RecordWithNullableSchemaAwareBackedEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\RecordWithSchemaAwareBackedEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObject;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObjectWithArrayAsArrayPropItem;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObjectWithInvalidDefaultProperties;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObjectWithNotListedArrayPropItem;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObjectWithSpecialKeySupport;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestStringVO;

use function json_decode;
use function json_encode;
use function serialize;
use function unserialize;

use const JSON_THROW_ON_ERROR;

class JsonSchemaAwareRecordLogicTest extends TestCase
{
    /** @return array<string, mixed> */
    public function objectData(): array
    {
        return [
            'test' => 'test',
            'testString' => TestStringVO::fromString('test'),
            'nonExistingProperty' => 'test',
            'list' => ['test'],
            'subObject' => ['test' => 'test'],
            'subObjects' => [
                ['test' => 'test'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function objectDataPlain(): array
    {
        $objectData = $this->objectData();
        $objectData['testString'] = 'test';

        return $objectData;
    }

    public function objectToTest(): TestObject
    {
        return TestObject::fromArray($this->objectData());
    }

    public function testObjectRemovesNonUsedProperties(): void
    {
        $test = $this->objectToTest();
        $data = $test->toArray();

        self::assertArrayNotHasKey('nonExistingProperty', $data);
    }

    public function testDefaultValuesFromPropertiesAreUsed(): void
    {
        $test = $this->objectToTest();
        $this->assertEquals(5, $test->normalValue());
    }

    public function testDefaultValuesFromFunctionAreUsed(): void
    {
        $test = $this->objectToTest();
        $this->assertEquals(0, $test->valueDefaultViaFunction()->toInt());
    }

    public function testDescriptionOnProperty(): void
    {
        $test   = $this->objectToTest();
        $schema = $test::__schema()->toArray();
        $this->assertEquals('Testdoc', $schema['properties']['test']['description']);
    }

    public function testExamplesAreAddedFromExamplesFunction(): void
    {
        $test   = $this->objectToTest();
        $schema = $test::__schema()->toArray();

        $this->assertEquals('test', $schema['properties']['test']['examples'][0]);
    }

    public function testExamplesAreAddedViaDocBlockOfProperty(): void
    {
        $test   = $this->objectToTest();
        $schema = $test::__schema()->toArray();

        $this->assertIsArray($schema['properties']['subObject']['examples'][0]);
    }

    public function testExamplesAreAddedViaValueObjectExamplesFunction(): void
    {
        $test   = $this->objectToTest();
        $schema = $test::__schema()->toArray();

        $this->assertIsString($schema['properties']['testString']['examples'][0]);
    }

    public function testSchemaAwareBackedEnumPropertyExtractsEnumSchema(): void
    {
        $schema = RecordWithSchemaAwareBackedEnum::__schema()->toArray();

        $this->assertSame('integer', $schema['properties']['priority']['type']);
        $this->assertSame([1, 2], $schema['properties']['priority']['enum']);
    }

    public function testNullableSchemaAwareBackedEnumPropertyExtractsNullableEnumSchema(): void
    {
        $schema = RecordWithNullableSchemaAwareBackedEnum::__schema()->toArray();

        $this->assertSame(['string', 'null'], $schema['properties']['status']['type']);
        $this->assertSame(['active', 'inactive', null], $schema['properties']['status']['enum']);
    }

    public function testNullableSchemaAwareBackedEnumPropertyHandlesNullValue(): void
    {
        $record = RecordWithNullableSchemaAwareBackedEnum::fromArray(['status' => null]);

        $this->assertSame(['status' => null], $record->toArray());
    }

    public function testSubImmutableRecord(): void
    {
        $test = $this->objectToTest();
        $data = $test->toArray();

        $this->assertArrayHasKey('subObject', $data);
        $this->assertIsArray($data['subObject']);
        $this->assertArrayHasKey('test', $data['subObject']);
    }

    public function testObjectWithNotListedArrayPropItem(): void
    {
        $this->expectExceptionMessageMatches('/Missing array item type in array property map./');
        $test = TestObjectWithNotListedArrayPropItem::fromArray(
            [
                'list' => ['test'],
            ],
        );

        $test::__schema();
    }

    public function testObjectWithArrayAsArrayPropItem(): void
    {
        $this->expectExceptionMessageMatches('/must not be \'array\'/');
        $test = TestObjectWithArrayAsArrayPropItem::fromArray(
            [
                'list' => [],
            ],
        );

        $test::__schema();
    }

    public function testUseDefaultMaxValues(): void
    {
        $test = TestObject::fromArrayWithDefaultMaxValues(
            [
                'test' => 'test',
                'testString' => 'test',
                'list' => ['test'],
            ],
        );

        $this->assertEquals(10, $test->normalValue());
    }

    public function testSpecialKeySupport(): void
    {
        $test = TestObjectWithSpecialKeySupport::fromArray(
            ['testCamelCase' => 'test'],
        );

        $data = $test->toArray();

        $this->assertEquals('test', $data['blabla']);
    }

    public function testInvalidDefaultPropertiesFunction(): void
    {
        $this->expectExceptionMessageMatches('/should be an associative array/');
        $test = TestObjectWithInvalidDefaultProperties::fromArray(
            ['test' => 'test'],
        );
    }

    public function testToJson(): void
    {
        $test = $this->objectToTest();
        $json = $test->toJson();

        $this->assertJson($json);
        $this->assertEquals(json_decode($json, true, 512, JSON_THROW_ON_ERROR), $test->toArray());
    }

    public function testFromJson(): void
    {
        $test = TestObject::fromJson(json_encode($this->objectDataPlain(), JSON_THROW_ON_ERROR));
        $this->assertInstanceOf(TestObject::class, $test);
    }

    public function testToYaml(): void
    {
        $test = $this->objectToTest();
        $yaml = $test->toYaml();

        $this->assertEquals(Yaml::parse($yaml), $test->toArray());
    }

    public function testFromYaml(): void
    {
        $test = TestObject::fromYaml(Yaml::dump($this->objectDataPlain()));
        $this->assertInstanceOf(TestObject::class, $test);
    }

    /**
     * This only occurs when the object itself has never been initialized.
     * Then the static property $__propTypeMap won't be set.
     * This specifically occurs when using a file based (serialized) cache
     */
    public function testSerializeObjectCanBeDeserializedIfNoPropTypeMapSet(): void
    {
        $object = $this->objectToTest();
        $serializedObject = serialize($object);
        $this->assertIsString($serializedObject);
        $this->assertInstanceOf(TestObject::class, $object);
        $this->assertIsArray(TestObject::getPropTypeMap());

        TestObject::resetPropTypeMap();
        $this->assertNull(TestObject::getPropTypeMap());

        $deserializedObject = unserialize($serializedObject);
        $this->assertInstanceOf(TestObject::class, $deserializedObject);
        $this->assertIsArray(TestObject::getPropTypeMap());
        $this->assertNotEmpty($deserializedObject->toArray());
    }
}
