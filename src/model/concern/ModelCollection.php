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
declare (strict_types = 1);

namespace think\model\concern;

use think\model\contract\Modelable as Model;

/**
 * 模型数据转换处理.
 */
trait ModelCollection
{
    public function toView(string $view)
    {
        return $this->map(function (Model $model) use($view) {
            return $model->toView($view);
        });
    }

    /**
     * 删除数据集的数据.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $this->each(function (Model $model) {
            $model->delete();
        });

        return true;
    }

    /**
     * 更新数据.
     *
     * @param array $data       数据数组
     * @param array $allowField 允许字段
     *
     * @return bool
     */
    public function update(array $data, array $allowField = []): bool
    {
        $this->each(function (Model $model) use ($data, $allowField) {
            if (!empty($allowField)) {
                $model->allowField($allowField);
            }

            $model->save($data);
        });

        return true;
    }

    /**
     * 设置需要隐藏的输出属性.
     *
     * @param array $hidden 属性列表
     * @param bool  $merge  是否合并
     *
     * @return $this
     */
    public function hidden(array $hidden, bool $merge = false)
    {
        $this->each(function (Model $model) use ($hidden, $merge) {
            $model->hidden($hidden, $merge);
        });

        return $this;
    }

    /**
     * 设置需要输出的属性.
     *
     * @param array $visible
     * @param bool  $merge   是否合并
     *
     * @return $this
     */
    public function visible(array $visible, bool $merge = false)
    {
        $this->each(function (Model $model) use ($visible, $merge) {
            $model->visible($visible, $merge);
        });

        return $this;
    }

    /**
     * 设置需要追加的输出属性.
     *
     * @param array $append 属性列表
     * @param bool  $merge  是否合并
     *
     * @return $this
     */
    public function append(array $append, bool $merge = false)
    {
        $this->each(function (Model $model) use ($append, $merge) {
            $model->append($append, $merge);
        });

        return $this;
    }

    /**
     * 设置属性映射.
     *
     * @param array $mapping 属性映射
     *
     * @return $this
     */
    public function mapping(array $mapping)
    {
        $this->each(function (Model $model) use ($mapping) {
            $model->mapping($mapping);
        });

        return $this;
    }

    /**
     * 设置模型输出场景.
     *
     * @param string $scene 场景名称
     *
     * @return $this
     */
    public function scene(string $scene)
    {
        $this->each(function (Model $model) use ($scene) {
            $model->scene($scene);
        });

        return $this;
    }

    /**
     * 设置数据字段获取器.
     *
     * @param string|array $name     字段名
     * @param callable     $callback 闭包获取器
     *
     * @return $this
     */
    public function withAttr(string|array $name, ?callable $callback = null)
    {
        $this->each(function (Model $model) use ($name, $callback) {
            $model->withFieldAttr($name, $callback);
        });

        return $this;
    }

    /**
     * 绑定（一对一）关联属性到当前模型.
     *
     * @param string $relation 关联名称
     * @param array  $attrs    绑定属性
     *
     * @throws Exception
     *
     * @return $this
     */
    public function bindAttr(string $relation, array $attrs = [])
    {
        $this->each(function (Model $model) use ($relation, $attrs) {
            $model->bindAttr($relation, $attrs);
        });

        return $this;
    } 
}
