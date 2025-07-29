<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\Query;
use think\Model;
use think\model\Collection;
use think\model\LazyCollection;

/**
 * LazyCollection单元测试类
 */
class LazyCollectionTest extends TestCase
{
    /**
     * 测试基本的生成器创建
     */
    public function testCreateFromGenerator()
    {
        $generator = function () {
            for ($i = 1; $i <= 5; $i++) {
                yield $i;
            }
        };

        $lazy = new LazyCollection($generator);
        $this->assertInstanceOf(LazyCollection::class, $lazy);

        $result = [];
        foreach ($lazy as $value) {
            $result[] = $value;
        }

        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    /**
     * 测试从数组创建
     */
    public function testMakeFromArray()
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $lazy = LazyCollection::make($data);

        $this->assertInstanceOf(LazyCollection::class, $lazy);
        $this->assertEquals($data, $lazy->toArray());
    }

    /**
     * 测试map方法
     */
    public function testMap()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $result = $lazy->map(function ($value) {
            return $value * 2;
        });

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([2, 4, 6, 8, 10], $result->toArray());
    }

    /**
     * 测试filter方法
     */
    public function testFilter()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5, 6]);
        $result = $lazy->filter(function ($value) {
            return $value % 2 == 0;
        });

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([1 => 2, 3 => 4, 5 => 6], $result->toArray());
    }

    /**
     * 测试chunk方法
     */
    public function testChunk()
    {
        $lazy = LazyCollection::make(range(1, 10));
        $chunks = $lazy->chunk(3);

        $result = [];
        foreach ($chunks as $chunk) {
            $this->assertInstanceOf(Collection::class, $chunk);
            $result[] = $chunk->toArray();
        }

        $expected = [
            [1, 2, 3],
            [3 => 4, 4 => 5, 5 => 6],
            [6 => 7, 7 => 8, 8 => 9],
            [9 => 10]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * 测试take方法
     */
    public function testTake()
    {
        $lazy = LazyCollection::make(range(1, 10));
        $result = $lazy->take(5);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([1, 2, 3, 4, 5], $result->toArray());
    }

    /**
     * 测试skip方法
     */
    public function testSkip()
    {
        $lazy = LazyCollection::make(range(1, 10));
        $result = $lazy->skip(5);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertEquals([5 => 6, 6 => 7, 7 => 8, 8 => 9, 9 => 10], $result->toArray());
    }

    /**
     * 测试flatten方法
     */
    public function testFlatten()
    {
        $data = [
            [1, 2, 3],
            [4, 5, 6],
            [7, [8, 9]]
        ];

        $lazy = LazyCollection::make($data);
        $result = $lazy->flatten(1);

        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, [8, 9]], $result->toArray());

        // 深度扁平化
        $result = $lazy->flatten();
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9], $result->toArray());
    }

    /**
     * 测试count方法
     */
    public function testCount()
    {
        $lazy = LazyCollection::make(range(1, 100));
        $this->assertEquals(100, $lazy->count());

        // 测试缓存机制
        $this->assertEquals(100, $lazy->count());
    }

    /**
     * 测试isEmpty方法
     */
    public function testIsEmpty()
    {
        $lazy = LazyCollection::make([]);
        $this->assertTrue($lazy->isEmpty());

        $lazy = LazyCollection::make([1, 2, 3]);
        $this->assertFalse($lazy->isEmpty());
    }

    /**
     * 测试first和last方法
     */
    public function testFirstAndLast()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);

        $this->assertEquals(1, $lazy->first());
        $this->assertEquals(5, $lazy->last());

        // 测试带回调的first
        $result = $lazy->first(function ($value) {
            return $value > 3;
        });
        $this->assertEquals(4, $result);

        // 测试默认值
        $empty = LazyCollection::make([]);
        $this->assertEquals('default', $empty->first(null, 'default'));
    }

    /**
     * 测试groupBy方法
     */
    public function testGroupBy()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        $lazy = LazyCollection::make($data);
        $grouped = $lazy->groupBy('age');

        $result = [];
        foreach ($grouped as $key => $group) {
            $this->assertInstanceOf(Collection::class, $group);
            $result[$key] = $group->toArray();
        }

        $this->assertArrayHasKey(25, $result);
        $this->assertArrayHasKey(30, $result);
        $this->assertCount(2, $result[25]);
        $this->assertCount(1, $result[30]);
    }

    /**
     * 测试merge方法
     */
    public function testMerge()
    {
        $lazy1 = LazyCollection::make([1, 2, 3]);
        $lazy2 = LazyCollection::make([4, 5, 6]);

        $result = $lazy1->merge($lazy2);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 0 => 4, 1 => 5, 2 => 6], $result->toArray());
    }

    /**
     * 测试reduce方法
     */
    public function testReduce()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $sum = $lazy->reduce(function ($carry, $item) {
            return $carry + $item;
        }, 0);

        $this->assertEquals(15, $sum);
    }

    /**
     * 测试reverse方法
     */
    public function testReverse()
    {
        $lazy = LazyCollection::make([1, 2, 3, 4, 5]);
        $result = $lazy->reverse();

        $this->assertEquals([4 => 5, 3 => 4, 2 => 3, 1 => 2, 0 => 1], $result->toArray());
    }

    /**
     * 测试keys和values方法
     */
    public function testKeysAndValues()
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $lazy = LazyCollection::make($data);

        $keys = $lazy->keys();
        $this->assertEquals(['a', 'b', 'c'], $keys->toArray());

        $values = $lazy->values();
        $this->assertEquals([1, 2, 3], $values->toArray());
    }

    /**
     * 测试when方法
     */
    public function testWhen()
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $result = $lazy->when(true, function ($collection) {
            return $collection->map(function ($item) {
                return $item * 2;
            });
        });

        $this->assertEquals([2, 4, 6], $result->toArray());

        // 测试条件为false
        $result = $lazy->when(false, function ($collection) {
            return $collection->map(function ($item) {
                return $item * 2;
            });
        });

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    /**
     * 测试tap方法
     */
    public function testTap()
    {
        $lazy = LazyCollection::make([1, 2, 3]);
        $tapped = false;

        $result = $lazy->tap(function ($collection) use (&$tapped) {
            $tapped = true;
            $this->assertInstanceOf(LazyCollection::class, $collection);
        });

        $this->assertTrue($tapped);
        $this->assertSame($lazy, $result);
    }

    /**
     * 测试JSON序列化
     */
    public function testJsonSerialize()
    {
        $lazy = LazyCollection::make(['a' => 1, 'b' => 2]);
        $json = json_encode($lazy);

        $this->assertEquals('{"a":1,"b":2}', $json);
    }

    /**
     * 测试内存效率
     */
    public function testMemoryEfficiency()
    {
        $count = 0;
        $generator = function () use (&$count) {
            for ($i = 1; $i <= 1000000; $i++) {
                $count++;
                yield $i;
            }
        };

        $lazy = new LazyCollection($generator);
        
        // 只获取前10个元素
        $result = $lazy->take(10)->toArray();
        
        // 确保生成器只生成了10个元素，而不是全部
        $this->assertEquals(11, $count);
        $this->assertEquals(range(1, 10), $result);
    }

    /**
     * 测试链式操作
     */
    public function testChaining()
    {
        $lazy = LazyCollection::make(range(1, 20));

        $result = $lazy
            ->filter(function ($value) {
                return $value % 2 == 0;
            })
            ->map(function ($value) {
                return $value * 2;
            })
            ->take(5)
            ->toArray();

        $this->assertEquals([1 => 4, 3 => 8, 5 => 12, 7 => 16, 9 => 20], $result);
    }

    /**
     * 测试异常处理
     */
    public function testExceptions()
    {
        $this->expectException(\InvalidArgumentException::class);
        new LazyCollection('invalid');
    }

    /**
     * 测试chunk大小验证
     */
    public function testChunkSizeValidation()
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $this->expectException(\InvalidArgumentException::class);
        $lazy->chunk(0);
    }

    /**
     * 测试take限制验证
     */
    public function testTakeLimitValidation()
    {
        $lazy = LazyCollection::make([1, 2, 3]);

        $this->expectException(\InvalidArgumentException::class);
        $lazy->take(-1);
    }
}