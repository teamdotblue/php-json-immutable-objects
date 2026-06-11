<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;
use TeamBlue\JsonImmutableObjects\Serializer\ImmutableRecordNormalizer;
use TeamBlue\JsonImmutableObjects\Tests\Object\RecordWithStringBackedEnum;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumStatus;

class ImmutableRecordNormalizerTest extends TestCase
{
    public function testSupportsImmutableRecords(): void
    {
        $normalizer = new ImmutableRecordNormalizer();

        $this->assertTrue($normalizer->supportsDenormalization([], RecordWithStringBackedEnum::class));
        $this->assertTrue(
            $normalizer->supportsNormalization(RecordWithStringBackedEnum::fromArray(['status' => 'active'])),
        );
        $this->assertFalse($normalizer->supportsDenormalization([], self::class));
        $this->assertFalse($normalizer->supportsNormalization($this));
    }

    public function testDenormalizeBuildsRecordViaFromArray(): void
    {
        $normalizer = new ImmutableRecordNormalizer();

        $record = $normalizer->denormalize(['status' => 'active'], RecordWithStringBackedEnum::class);

        $this->assertInstanceOf(RecordWithStringBackedEnum::class, $record);
        $this->assertSame(BackedEnumStatus::ACTIVE, $record->status);
    }

    public function testNormalizeDelegatesToToArray(): void
    {
        $normalizer = new ImmutableRecordNormalizer();
        $record = RecordWithStringBackedEnum::fromArray(['status' => 'inactive']);

        $this->assertSame(['status' => 'inactive'], $normalizer->normalize($record));
    }

    /**
     * Regression guard: wired into a Serializer the record is built through
     * ::fromArray(), so the subsequent ->toArray() round-trips instead of
     * collapsing to [] (the data-loss bug that occurs when ObjectNormalizer
     * builds the record via newInstanceWithoutConstructor()).
     */
    public function testRoundTripsThroughSerializerWithoutDataLoss(): void
    {
        $serializer = new Serializer([new ImmutableRecordNormalizer()]);

        $record = $serializer->denormalize(['status' => 'active'], RecordWithStringBackedEnum::class);

        $this->assertInstanceOf(RecordWithStringBackedEnum::class, $record);
        $this->assertSame(['status' => 'active'], $record->toArray());
    }
}
