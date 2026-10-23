<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum;

use TeamBlue\ValueObjects\Implementation\Enum\IntBackedEnumValue;

enum BackedEnumPriority: int
{
    use IntBackedEnumValue;

    case LOW = 1;
    case HIGH = 2;
}
