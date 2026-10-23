<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class TestObjectWithNotListedArrayPropItem implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    /** @var array<string> */
    private array $list;

    /** @return array<string, string> */
    private static function arrayPropItemTypeMap(): array
    {
        return [];
    }
}
