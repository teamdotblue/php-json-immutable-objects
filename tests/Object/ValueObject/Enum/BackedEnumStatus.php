<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum;

use TeamBlue\ValueObjects\Implementation\Enum\StringBackedEnumValue;

enum BackedEnumStatus: string
{
    use StringBackedEnumValue;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
