<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class OneOfWithDiscriminatorOne implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private string $type;
    private int $value;

    public function value(): int
    {
        return $this->value;
    }
}
