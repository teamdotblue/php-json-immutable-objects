<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\Data\SpecialKeySupport;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class TestObjectWithSpecialKeySupport implements JsonSchemaAwareRecord, SpecialKeySupport
{
    use JsonSchemaAwareRecordLogic;

    private string $testOtherCamelCase;

    public function convertKeyForRecord(string $key): string
    {
        return match ($key) {
            'testCamelCase' => 'testOtherCamelCase',
            default => $key,
        };
    }

    public function convertKeyForArray(string $key): string
    {
        return match ($key) {
            'testOtherCamelCase' => 'blabla',
            default => $key,
        };
    }
}
