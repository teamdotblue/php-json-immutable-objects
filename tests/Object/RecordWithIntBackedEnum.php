<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use EventEngine\Data\ImmutableRecord;
use TeamBlue\JsonImmutableObjects\ImmutableRecordLogic;
use TeamBlue\JsonImmutableObjects\Tests\Object\ValueObject\Enum\BackedEnumPriority;

final class RecordWithIntBackedEnum implements ImmutableRecord
{
    use ImmutableRecordLogic;

    public BackedEnumPriority $priority;
}
