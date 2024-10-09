<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\List;

use ADS\ValueObjects\Implementation\ListValue\JsonSchemaAwareCollectionLogic;
use ADS\ValueObjects\Implementation\ListValue\ListValue;
use TeamBlue\JsonImmutableObjects\Tests\Object\Immutable\TestImmutable;

/** @extends ListValue<TestImmutable> */
class TestListImmutable extends ListValue
{
    use JsonSchemaAwareCollectionLogic;

    public static function itemType(): string
    {
        return TestImmutable::class;
    }
}
