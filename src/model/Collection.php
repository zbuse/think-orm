<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\model;

use think\Collection as BaseCollection;
use think\model\concern\ModelCollection;
use think\model\contract\Modelable as Model;
use think\Paginator;

/**
 * 模型数据集类.
 *
 * @template TKey of array-key
 * @template TModel of \think\Model
 *
 * @extends BaseCollection<TKey, TModel>
 */
class Collection extends BaseCollection
{
    use ModelCollection;
    /**
     * 延迟预载入关联查询.
     *
     * @param array $relation 关联
     * @param mixed $cache    关联缓存
     *
     * @return $this
     */
    public function load(array $relation, $cache = false)
    {
        if (!$this->isEmpty()) {
            $item = current($this->items);
            $item->eagerlyResultSet($this->items, $relation, [], false, $cache);
        }

        return $this;
    }

    /**
     * 按指定键整理数据.
     *
     * @param mixed       $items    数据
     * @param string|null $indexKey 键名
     *
     * @return array
     */
    public function dictionary($items = null, ?string &$indexKey = null)
    {
        if ($items instanceof self || $items instanceof Paginator) {
            $items = $items->all();
        }

        $items = is_null($items) ? $this->items : $items;

        if ($items && empty($indexKey)) {
            $indexKey = $items[0]->getPk();
        }

        if (isset($indexKey) && is_string($indexKey)) {
            return array_column($items, null, $indexKey);
        }

        return $items;
    }

    /**
     * 比较数据集，返回差集.
     *
     * @param mixed       $items    数据
     * @param string|null $indexKey 指定比较的键名
     *
     * @return static
     */
    public function diff($items, ?string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static($items);
        }

        $diff = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (!isset($dictionary[$item[$indexKey]])) {
                    $diff[] = $item;
                }
            }
        }

        return new static($diff);
    }

    /**
     * 比较数据集，返回交集.
     *
     * @param mixed       $items    数据
     * @param string|null $indexKey 指定比较的键名
     *
     * @return static
     */
    public function intersect($items, ?string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static([]);
        }

        $intersect = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (isset($dictionary[$item[$indexKey]])) {
                    $intersect[] = $item;
                }
            }
        }

        return new static($intersect);
    }
}
