<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Object;

use TeamBlue\JsonImmutableObjects\FactoryFromArray;

class TestObjectFactory
{
    use FactoryFromArray;

    protected static function modelClass(): string
    {
        return TestObject::class;
    }
}
