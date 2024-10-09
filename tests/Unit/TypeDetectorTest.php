<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use DateTime;
use EventEngine\JsonSchema\Type\ArrayType;
use EventEngine\JsonSchema\Type\BoolType;
use EventEngine\JsonSchema\Type\FloatType;
use EventEngine\JsonSchema\Type\IntEnumType;
use EventEngine\JsonSchema\Type\IntType;
use EventEngine\JsonSchema\Type\ObjectType;
use EventEngine\JsonSchema\Type\StringEnumType;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\TypeRef;
use PHPUnit\Framework\TestCase;
use TeamBlue\JsonImmutableObjects\Tests\Object\Immutable\TestImmutable;
use TeamBlue\JsonImmutableObjects\Tests\Object\RandomClass;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Bool\TestBool;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\IntEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\StringEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Float\TestFloat;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Int\TestInt;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\List\TestList;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\List\TestListImmutable;
use TeamBlue\JsonImmutableObjects\TypeDetector;

/** @SuppressWarnings(PHPMD.CouplingBetweenObjects) */
class TypeDetectorTest extends TestCase
{
    public function testTypeOfString(): void
    {
        $type = TypeDetector::typeFromClass('test');

        $this->assertInstanceOf(TypeRef::class, $type);
    }

    public function testTypeOfClass(): void
    {
        $type = TypeDetector::typeFromClass(TestImmutable::class);

        $this->assertInstanceOf(ObjectType::class, $type);
    }

    public function testTypeOfList(): void
    {
        $type = TypeDetector::typeFromClass(TestList::class);

        $this->assertInstanceOf(ArrayType::class, $type);
    }

    public function testTypeOfListWithImmutableTypes(): void
    {
        $type = TypeDetector::typeFromClass(TestListImmutable::class);

        $this->assertInstanceOf(ArrayType::class, $type);
    }

    public function testIntEnumType(): void
    {
        $type = TypeDetector::typeFromClass(IntEnum::class);

        $this->assertInstanceOf(IntEnumType::class, $type);
    }

    public function testStringEnumType(): void
    {
        $type = TypeDetector::typeFromClass(StringEnum::class);

        $this->assertInstanceOf(StringEnumType::class, $type);
    }

    public function testBooleanType(): void
    {
        $type = TypeDetector::typeFromClass(TestBool::class);

        $this->assertInstanceOf(BoolType::class, $type);
    }

    public function testIntType(): void
    {
        $type = TypeDetector::typeFromClass(TestInt::class);

        $this->assertInstanceOf(IntType::class, $type);
    }

    public function testFloatType(): void
    {
        $type = TypeDetector::typeFromClass(TestFloat::class);

        $this->assertInstanceOf(FloatType::class, $type);
    }

    public function testDateTime(): void
    {
        $type = TypeDetector::typeFromClass(DateTime::class);

        $this->assertInstanceOf(StringType::class, $type);
        $typeArray = $type->toArray();
        $this->assertArrayHasKey('format', $typeArray);
        $this->assertEquals('date-time', $typeArray['format']);
    }

    public function testUnknonwClass(): void
    {
        $type = TypeDetector::typeFromClass('unknown');

        $this->assertInstanceOf(TypeRef::class, $type);
    }

    public function testRandomClass(): void
    {
        $type = TypeDetector::typeFromClass(RandomClass::class);

        $this->assertInstanceOf(TypeRef::class, $type);
    }
}
