<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use EventEngine\JsonSchema\Type\ArrayType;
use EventEngine\JsonSchema\Type\ObjectType;
use EventEngine\JsonSchema\Type\StringEnumType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TeamBlue\JsonImmutableObjects\MetadataExtractor\JsonSchemaExtractor;
use TeamBlue\JsonImmutableObjects\Tests\Object\Immutable\TestCollection;
use TeamBlue\JsonImmutableObjects\Tests\Object\Immutable\TestImmutable;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumStatus;

class JsonSchemaExtractorTest extends TestCase
{
    private JsonSchemaExtractor $jsonSchemaExtractor;

    protected function setUp(): void
    {
        $this->jsonSchemaExtractor = new JsonSchemaExtractor();
    }

    public function testRecordFromReflectionClass(): void
    {
        $schema = $this->jsonSchemaExtractor->fromReflectionClass(
            new ReflectionClass(TestImmutable::class),
        );

        $this->assertInstanceOf(ObjectType::class, $schema);
    }

    public function testCollectionFromReflectionClass(): void
    {
        $schema = $this->jsonSchemaExtractor->fromReflectionClass(
            new ReflectionClass(TestCollection::class),
        );

        $this->assertInstanceOf(ArrayType::class, $schema);
    }

    public function testRecordFromClass(): void
    {
        $schema = $this->jsonSchemaExtractor->fromClass(TestImmutable::class);

        $this->assertInstanceOf(ObjectType::class, $schema);
    }

    public function testCollectionFromClass(): void
    {
        $schema = $this->jsonSchemaExtractor->fromClass(TestCollection::class);

        $this->assertInstanceOf(ArrayType::class, $schema);
    }

    public function testBackedEnumFromClass(): void
    {
        $schema = $this->jsonSchemaExtractor->fromClass(BackedEnumStatus::class);

        $this->assertInstanceOf(StringEnumType::class, $schema);
        $this->assertSame(['active', 'inactive'], $schema->toArray()['enum']);
    }

    public function testNonExistingSchemaExtractor(): void
    {
        $this->expectExceptionMessage(
            'No schema found for message \'TeamBlue\JsonImmutableObjects\MetadataExtractor\JsonSchemaExtractor\'. ' .
            'Implement the JsonSchemaAwareRecord or JsonSchemaAwareCollection interface.',
        );

        $this->jsonSchemaExtractor->fromReflectionClass(
            new ReflectionClass(JsonSchemaExtractor::class),
        );
    }
}
