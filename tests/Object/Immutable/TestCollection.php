<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\Immutable;

use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use TeamBlue\ValueObjects\Implementation\ListValue\JsonSchemaAwareCollectionLogic;

class TestCollection implements JsonSchemaAwareCollection
{
    use JsonSchemaAwareCollectionLogic;

    public static function itemType(): string
    {
        return TestImmutable::class;
    }
}
