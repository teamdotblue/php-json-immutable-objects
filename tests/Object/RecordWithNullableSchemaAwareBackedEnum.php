<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumStatus;

class RecordWithNullableSchemaAwareBackedEnum implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private BackedEnumStatus|null $status = null;
}
