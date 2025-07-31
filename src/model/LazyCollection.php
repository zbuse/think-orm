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

use think\db\LazyCollection as DbCollection;
use think\model\concern\ModelCollection;

/**
 * 模型惰性集合类 - 用于高效处理大数据集.
 *
 */
class LazyCollection extends DbCollection
{
    use ModelCollection;

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

            if (!empty($items)) {
                $first = reset($items);
                $first->eagerlyResultSet($items, $relation, [], false, $cache);
            }

            foreach ($items as $key => $item) {
                yield $key => $item;
            }
        });
    }

    /**
     * 按键值对集合进行分组
     * @param callable|string|int $groupBy 分组依据
     * @return static
     */
    public function group($groupBy)
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
}