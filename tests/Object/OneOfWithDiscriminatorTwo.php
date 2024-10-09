<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class OneOfWithDiscriminatorTwo implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private TypeVO $type;
    private string $other;
}
