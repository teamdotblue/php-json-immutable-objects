<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Discriminator\CamelCasedPropertyName;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class DiscriminatorItemOne implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private TypeVo $myType;
    private string $other;
}
