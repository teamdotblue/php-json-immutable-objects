<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Discriminator\CamelCasedPropertyName;

use TeamBlue\JsonImmutableObjects\Polymorphism\Discriminator;
use TeamBlue\JsonImmutableObjects\Polymorphism\DiscriminatorLogic;

class OneOfWithCamelCasedDiscriminator implements Discriminator
{
    use DiscriminatorLogic;

    public static function propertyName(): string
    {
        return 'my_type';
    }

    /** @inheritDoc */
    public static function jsonSchemaAwareRecords(): array
    {
        return [
            DiscriminatorItemOne::class,
            DiscriminatorItemTwo::class,
        ];
    }

    /** @return array<string, string> */
    public static function mapping(): array
    {
        return [
            TypeVo::ONE => DiscriminatorItemOne::class,
            TypeVo::TWO => DiscriminatorItemTwo::class,
        ];
    }
}
