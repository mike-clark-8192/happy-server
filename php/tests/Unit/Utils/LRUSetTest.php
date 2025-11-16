<?php

namespace Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Happy\Utils\LRUSet;

class LRUSetTest extends TestCase
{
    public function testThrowErrorWhenMaxSizeIsZeroOrNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LRUSet maxSize must be greater than 0');
        new LRUSet(0);
    }

    public function testThrowErrorForNegativeMaxSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LRUSet maxSize must be greater than 0');
        new LRUSet(-1);
    }

    public function testCreateLRUSetWithPositiveMaxSize(): void
    {
        $lru = new LRUSet(3);
        $this->assertEquals(0, $lru->size());
    }

    public function testAddValuesToTheSet(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);

        $this->assertEquals(3, $lru->size());
        $this->assertTrue($lru->has(1));
        $this->assertTrue($lru->has(2));
        $this->assertTrue($lru->has(3));
    }

    public function testNotDuplicateValues(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(1);
        $lru->add(1);

        $this->assertEquals(1, $lru->size());
        $this->assertTrue($lru->has(1));
    }

    public function testEvictLeastRecentlyUsedItemWhenCapacityExceeded(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);
        $lru->add(4); // Should evict 1

        $this->assertEquals(3, $lru->size());
        $this->assertFalse($lru->has(1));
        $this->assertTrue($lru->has(2));
        $this->assertTrue($lru->has(3));
        $this->assertTrue($lru->has(4));
    }

    public function testMoveAccessedItemsToFront(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);

        // Access 1, moving it to front
        $lru->has(1);

        // Add 4, should evict 2 (least recently used)
        $lru->add(4);

        $this->assertTrue($lru->has(1));
        $this->assertFalse($lru->has(2));
        $this->assertTrue($lru->has(3));
        $this->assertTrue($lru->has(4));
    }

    public function testMoveReAddedItemsToFront(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);

        // Re-add 1, moving it to front
        $lru->add(1);

        // Add 4, should evict 2 (least recently used)
        $lru->add(4);

        $this->assertTrue($lru->has(1));
        $this->assertFalse($lru->has(2));
        $this->assertTrue($lru->has(3));
        $this->assertTrue($lru->has(4));
    }

    public function testDeleteValues(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);

        $this->assertTrue($lru->delete(2));
        $this->assertEquals(2, $lru->size());
        $this->assertFalse($lru->has(2));

        $this->assertFalse($lru->delete(2)); // Already deleted
    }

    public function testHandleDeleteOfHeadNode(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3); // 3 is head

        $this->assertTrue($lru->delete(3));
        $this->assertEquals(2, $lru->size());
        $this->assertEquals([2, 1], $lru->toArray());
    }

    public function testHandleDeleteOfTailNode(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1); // 1 is tail
        $lru->add(2);
        $lru->add(3);

        $this->assertTrue($lru->delete(1));
        $this->assertEquals(2, $lru->size());
        $this->assertEquals([3, 2], $lru->toArray());
    }

    public function testClearAllValues(): void
    {
        $lru = new LRUSet(3);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);

        $lru->clear();

        $this->assertEquals(0, $lru->size());
        $this->assertFalse($lru->has(1));
        $this->assertFalse($lru->has(2));
        $this->assertFalse($lru->has(3));
    }

    public function testConvertToArrayInOrderFromMostToLeastRecentlyUsed(): void
    {
        $lru = new LRUSet(4);
        $lru->add(1);
        $lru->add(2);
        $lru->add(3);
        $lru->add(4);

        $this->assertEquals([4, 3, 2, 1], $lru->toArray());
    }

    public function testWorkWithStringValues(): void
    {
        $lru = new LRUSet(3);
        $lru->add('a');
        $lru->add('b');
        $lru->add('c');
        $lru->add('d');

        $this->assertFalse($lru->has('a'));
        $this->assertTrue($lru->has('b'));
        $this->assertTrue($lru->has('c'));
        $this->assertTrue($lru->has('d'));
    }

    public function testWorkWithObjectValues(): void
    {
        $lru = new LRUSet(2);
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 3];

        $lru->add($obj1);
        $lru->add($obj2);
        $lru->add($obj3);

        $this->assertFalse($lru->has($obj1));
        $this->assertTrue($lru->has($obj2));
        $this->assertTrue($lru->has($obj3));
    }

    public function testHandleSingleItemCapacity(): void
    {
        $lru = new LRUSet(1);
        $lru->add(1);
        $lru->add(2);

        $this->assertEquals(1, $lru->size());
        $this->assertFalse($lru->has(1));
        $this->assertTrue($lru->has(2));
    }

    public function testHandleOperationsOnEmptySet(): void
    {
        $lru = new LRUSet(3);

        $this->assertEquals(0, $lru->size());
        $this->assertFalse($lru->has(1));
        $this->assertFalse($lru->delete(1));
        $this->assertEquals([], $lru->toArray());
    }
}
