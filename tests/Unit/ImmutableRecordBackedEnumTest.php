<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TeamBlue\JsonImmutableObjects\Tests\Object\RecordWithIntBackedEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\RecordWithStringBackedEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumPriority;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumStatus;

class ImmutableRecordBackedEnumTest extends TestCase
{
    public function testRecordHydratesEnumPropertyFromScalar(): void
    {
        $record = RecordWithStringBackedEnum::fromArray(['status' => 'active']);

        $this->assertSame(BackedEnumStatus::ACTIVE, $record->status);
    }

    public function testRecordSerialisesEnumPropertyToScalar(): void
    {
        $record = RecordWithStringBackedEnum::fromArray(['status' => 'inactive']);

        $this->assertSame(['status' => 'inactive'], $record->toArray());
    }

    public function testRecordHydratesIntBackedEnumPropertyFromScalar(): void
    {
        $record = RecordWithIntBackedEnum::fromArray(['priority' => 2]);

        $this->assertSame(BackedEnumPriority::HIGH, $record->priority);
    }

    public function testRecordSerialisesIntBackedEnumPropertyToScalar(): void
    {
        $record = RecordWithIntBackedEnum::fromArray(['priority' => 1]);

        $this->assertSame(['priority' => 1], $record->toArray());
    }
}
