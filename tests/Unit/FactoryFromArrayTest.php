<?php

declare(strict_types=1);

namespace TeamBlue\JsonImmutableObjects\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObject;
use TeamBlue\JsonImmutableObjects\Tests\Object\TestObjectFactory;

class FactoryFromArrayTest extends TestCase
{
    public function testFactoryFromArray(): void
    {
        $test = TestObjectFactory::fromArray(
            [
                'test' => 'test',
                'testString' => 'test',
                'nonExistingProperty' => 'test',
                'list' => ['test'],
                'subObject' => ['test' => 'test'],
                'subObjects' => [
                    ['test' => 'test'],
                ],
            ],
        );

        $this->assertInstanceOf(TestObject::class, $test);
    }
}
