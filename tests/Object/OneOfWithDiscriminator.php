<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use TeamBlue\JsonImmutableObjects\Polymorphism\Discriminator;
use TeamBlue\JsonImmutableObjects\Polymorphism\DiscriminatorLogic;

class OneOfWithDiscriminator implements Discriminator
{
    use DiscriminatorLogic;

    public static function propertyName(): string
    {
        return 'type';
    }

    /** @inheritDoc */
    public static function jsonSchemaAwareRecords(): array
    {
        return [
            OneOfWithDiscriminatorOne::class,
            OneOfWithDiscriminatorTwo::class,
        ];
    }
}
