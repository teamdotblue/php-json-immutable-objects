<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Serializer;

use EventEngine\Data\ImmutableRecord;
use Override;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function assert;
use function is_a;
use function is_array;

/**
 * Bridges {@see ImmutableRecord} DTOs to their `::fromArray()` / `->toArray()`
 * contract when (de)serialising through the Symfony Serializer.
 *
 * Without this normalizer the default {@see \Symfony\Component\Serializer\Normalizer\ObjectNormalizer}
 * constructs records via `newInstanceWithoutConstructor()`, bypassing the
 * constructor that builds the trait's `$__propTypeMap`. The record then appears
 * populated, but the next `->toArray()` collapses to an empty array (data loss).
 * Routing records through `::fromArray()` constructs them correctly.
 */
final class ImmutableRecordNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function normalize(mixed $data, string|null $format = null, array $context = []): array
    {
        assert($data instanceof ImmutableRecord);

        /** @var array<string, mixed> $nativeData */
        $nativeData = $data->toArray();

        return $nativeData;
    }

    /** @param array<string, mixed> $context */
    #[Override]
    public function supportsNormalization(mixed $data, string|null $format = null, array $context = []): bool
    {
        return $data instanceof ImmutableRecord;
    }

    /** @param array<string, mixed> $context */
    #[Override]
    public function denormalize(mixed $data, string $type, string|null $format = null, array $context = []): mixed
    {
        assert(is_array($data));
        assert(is_a($type, ImmutableRecord::class, true));

        return $type::fromArray($data);
    }

    /** @param array<string, mixed> $context */
    #[Override]
    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): bool {
        return is_a($type, ImmutableRecord::class, true);
    }

    /** @return array<class-string, bool> */
    #[Override]
    public function getSupportedTypes(string|null $format): array
    {
        return [ImmutableRecord::class => true];
    }
}
