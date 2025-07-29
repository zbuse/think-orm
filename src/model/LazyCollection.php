<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\model;

use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\db\BaseQuery;
use think\Model;
use Traversable;

/**
 * 惰性集合类 - 用于高效处理大数据集.
 *
 * @template TKey of array-key
 * @template TModel of \think\Model
 *
 * @implements IteratorAggregate<TKey, TModel>
 */
class LazyCollection implements IteratorAggregate, Countable, JsonSerializable, Arrayable, Jsonable
{
    /**
     * 数据源生成器
     * @var \Closure|Generator
     */
    protected $source;

    /**
     * 缓存的总数
     * @var int|null
     */
    protected ?int $cachedCount = null;

    /**
     * 构造函数
     * @param \Closure|Generator|BaseQuery $source 数据源
     */
    public function __construct($source)
    {
        if ($source instanceof BaseQuery) {
            $this->source = function () use ($source) {
                yield from $source->cursor();
            };
        } elseif ($source instanceof \Closure) {
            $this->source = $source;
        } elseif ($source instanceof Generator) {
            $this->source = function () use ($source) {
                yield from $source;
            };
        } else {
            throw new \InvalidArgumentException('Invalid source type for LazyCollection');
        }
    }

    /**
     * 创建惰性集合实例
     * @param mixed $items 数据源
     * @return static
     */
    public static function make($items = [])
    {
        if ($items instanceof static) {
            return $items;
        }

        if (is_array($items)) {
            return new static(function () use ($items) {
                foreach ($items as $key => $value) {
                    yield $key => $value;
                }
            });
        }

        return new static($items);
    }

    /**
     * 获取生成器
     * @return Generator
     */
    public function getIterator(): Traversable
    {
        return ($this->source)();
    }

    /**
     * 映射集合项到新的惰性集合
     * @param callable $callback 回调函数
     * @return static
     */
    public function map(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * 过滤集合项
     * @param callable|null $callback 回调函数
     * @return static
     */
    public function filter(?callable $callback = null)
    {
        if (is_null($callback)) {
            $callback = function ($value) {
                return (bool) $value;
            };
        }

        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * 遍历集合项
     * @param callable $callback 回调函数
     * @return void
     */
    public function each(callable $callback): void
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }
    }

    /**
     * 分块处理数据
     * @param int $size 块大小
     * @return static
     */
    public function chunk(int $size)
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Size should be greater than 0');
        }

        return new static(function () use ($size) {
            $chunk = [];
            $count = 0;

            foreach ($this->getIterator() as $key => $value) {
                $chunk[$key] = $value;
                $count++;

                if ($count >= $size) {
                    yield new Collection($chunk);
                    $chunk = [];
                    $count = 0;
                }
            }

            if (!empty($chunk)) {
                yield new Collection($chunk);
            }
        });
    }

    /**
     * 获取前N个元素
     * @param int $limit 数量限制
     * @return static
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('Limit should be non-negative');
        }

        return new static(function () use ($limit) {
            $count = 0;
            foreach ($this->getIterator() as $key => $value) {
                if ($count >= $limit) {
                    break;
                }
                yield $key => $value;
                $count++;
            }
        });
    }

    /**
     * 跳过前N个元素
     * @param int $offset 跳过数量
     * @return static
     */
    public function skip(int $offset)
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('Offset should be non-negative');
        }

        return new static(function () use ($offset) {
            $count = 0;
            foreach ($this->getIterator() as $key => $value) {
                if ($count >= $offset) {
                    yield $key => $value;
                }
                $count++;
            }
        });
    }

    /**
     * 扁平化集合
     * @param int|float $depth 深度
     * @return static
     */
    public function flatten($depth = INF)
    {
        return new static(function () use ($depth) {
            foreach ($this->getIterator() as $item) {
                if (is_array($item) || $item instanceof Traversable) {
                    if ($depth === 1) {
                        foreach ($item as $value) {
                            yield $value;
                        }
                    } else {
                        foreach (static::make($item)->flatten($depth - 1) as $value) {
                            yield $value;
                        }
                    }
                } else {
                    yield $item;
                }
            }
        });
    }

    /**
     * 延迟预载入关联查询
     * @param array $relation 关联
     * @param mixed $cache    关联缓存
     * @return static
     */
    public function load(array $relation, $cache = false)
    {
        return new static(function () use ($relation, $cache) {
            $items = [];
            foreach ($this->getIterator() as $key => $item) {
                $items[$key] = $item;
            }

            if (!empty($items) && $items[0] instanceof Model) {
                $first = reset($items);
                $first->eagerlyResultSet($items, $relation, [], false, $cache);
            }

            foreach ($items as $key => $item) {
                yield $key => $item;
            }
        });
    }

    /**
     * 转换为普通Collection
     * @return Collection
     */
    public function toCollection(): Collection
    {
        return new Collection($this->toArray());
    }

    /**
     * 转换为数组
     * @return array
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->getIterator() as $key => $value) {
            if ($value instanceof Arrayable) {
                $items[$key] = $value->toArray();
            } else {
                $items[$key] = $value;
            }
        }
        return $items;
    }

    /**
     * 转换为JSON
     * @param int $options JSON选项
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * JsonSerializable接口实现
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 获取集合数量
     * @return int
     */
    public function count(): int
    {
        if ($this->cachedCount !== null) {
            return $this->cachedCount;
        }

        $count = 0;
        foreach ($this->getIterator() as $value) {
            $count++;
        }

        $this->cachedCount = $count;
        return $count;
    }

    /**
     * 判断是否为空
     * @return bool
     */
    public function isEmpty(): bool
    {
        foreach ($this->getIterator() as $value) {
            return false;
        }
        return true;
    }

    /**
     * 获取第一个元素
     * @param callable|null $callback 回调函数
     * @param mixed $default 默认值
     * @return mixed
     */
    public function first(?callable $callback = null, $default = null)
    {
        foreach ($this->getIterator() as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * 获取最后一个元素
     * @param callable|null $callback 回调函数
     * @param mixed $default 默认值
     * @return mixed
     */
    public function last(?callable $callback = null, $default = null)
    {
        $last = $default;
        $lastKey = null;

        foreach ($this->getIterator() as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $last = $value;
                $lastKey = $key;
            }
        }

        return $last;
    }

    /**
     * 按键值对集合进行分组
     * @param callable|string $groupBy 分组依据
     * @return static
     */
    public function groupBy($groupBy)
    {
        return new static(function () use ($groupBy) {
            $groups = [];

            foreach ($this->getIterator() as $key => $value) {
                if (is_callable($groupBy)) {
                    $groupKey = $groupBy($value, $key);
                } else {
                    $groupKey = data_get($value, $groupBy);
                }

                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [];
                }
                $groups[$groupKey][$key] = $value;
            }

            foreach ($groups as $key => $group) {
                yield $key => new Collection($group);
            }
        });
    }

    /**
     * 合并其他集合
     * @param mixed $items 要合并的数据
     * @return static
     */
    public function merge($items)
    {
        return new static(function () use ($items) {
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $value;
            }

            foreach (static::make($items)->getIterator() as $key => $value) {
                yield $key => $value;
            }
        });
    }

    /**
     * 获取指定键的值
     * @param mixed $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null)
    {
        foreach ($this->getIterator() as $k => $value) {
            if ($k === $key) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * 对集合进行排序
     * @param callable|null $callback 排序回调
     * @return Collection
     */
    public function sort(?callable $callback = null): Collection
    {
        $items = $this->toArray();

        if (is_null($callback)) {
            asort($items);
        } else {
            uasort($items, $callback);
        }

        return new Collection($items);
    }

    /**
     * 对集合进行反转
     * @return static
     */
    public function reverse()
    {
        return new static(function () {
            $items = [];
            foreach ($this->getIterator() as $key => $value) {
                $items[] = [$key, $value];
            }

            for ($i = count($items) - 1; $i >= 0; $i--) {
                yield $items[$i][0] => $items[$i][1];
            }
        });
    }

    /**
     * 获取集合的所有键
     * @return static
     */
    public function keys()
    {
        return new static(function () {
            foreach ($this->getIterator() as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * 获取集合的所有值
     * @return static
     */
    public function values()
    {
        return new static(function () {
            foreach ($this->getIterator() as $value) {
                yield $value;
            }
        });
    }

    /**
     * 使用回调函数迭代集合项并将结果传递给下一次迭代
     * @param callable $callback 回调函数
     * @param mixed $initial 初始值
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        $result = $initial;

        foreach ($this->getIterator() as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * 执行给定的回调，除非给定的条件为真
     * @param bool $condition 条件
     * @param callable $callback 回调函数
     * @param callable|null $default 默认回调
     * @return static
     */
    public function when(bool $condition, callable $callback, ?callable $default = null)
    {
        if ($condition) {
            return $callback($this) ?: $this;
        } elseif ($default) {
            return $default($this) ?: $this;
        }

        return $this;
    }

    /**
     * 为集合内的每一个元素执行回调
     * @param callable $callback 回调函数
     * @return static
     */
    public function tap(callable $callback)
    {
        $callback($this);
        return $this;
    }
}