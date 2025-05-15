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

namespace think\model;

use ReflectionClass;
use ReflectionProperty;
use think\Entity;
use think\exception\ValidateException;
use think\helper\Str;
use think\Model;
use think\model\Collection;
use think\model\contract\Modelable;
use think\model\type\Json;

/**
 * 视图模型
 */
abstract class View extends Entity
{
    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     * @param bool  $with  是否存在with关联查询
     */
    public function __construct(?Model $model = null, bool $with = false)
    {
        parent::__construct($model);

        // 初始化模型
        $this->initData(!$with);
    }

    /**
     * 初始化实体数据属性（如果存在关联查询则会延迟执行）.
     *
     * @param bool  $relation  是否处理关联数据
     * @return void
     */
    public function initData(bool $relation = true)
    {
        // 获取实体属性
        $properties = $this->getEntityPropertiesMap();
        $data       = $this->model()->getData();
        if (!empty($data)) {
            foreach ($properties as $key => $field) {
                if (!$relation) {
                    // 确保存在基础模型数据
                    if (isset($data[$field])) { 
                        $this->$field = $data[$field];
                    }
                    continue;
                }

                if (is_int($key)) {
                    $this->$field = $this->fetchViewAttr($field);
                } elseif (strpos($field, '->')) {
                    $items    = explode('->', $field);
                    $relation = array_shift($items);
                    if (isset($data[$relation])) {
                        // 存在关联数据
                        $value = $data[$relation];
                        foreach ($items as $item) {
                            if ($value instanceof Json) {
                                // JSON字段数据
                                $value = $this->readTransformJson($relation, $value);
                            }

                            if (is_array($value)) {
                                $value = $value[$item] ?? null;
                            } elseif (is_object($value)) {
                                $value = $value->$item ?? null;
                            }
                        }
                        $this->$key = $value;
                    }
                } else {
                    $this->$key = $this->fetchViewAttr($field);
                }
            }
        }
    }

    /**
     * 获取视图属性值（支持视图获取器）.
     *
     * @param string $field 视图属性
     *
     * @return mixed
     */
    private function fetchViewAttr(string $field)
    {
        $method = 'get' . Str::camel($field) . 'Attr';
        $model  = $this->model();
        if (method_exists($this, $method)) {
            $value = $this->$method($model); 
        } elseif ($model->hasData($field)) {
            $value = $model->$field;
        } else {
            $relations = $this->getOption('autoMapping', []);
            $value     = null;
            foreach ($relations as $relation) {
                if ($model->$relation?->hasData($field)) {
                    $value   = $model->$relation->$field;
                    $mapping = $this->getOption('autoMappingAlias', []);

                    $mapping[$field] = $relation . '->' . $field;
                    $this->setOption('autoMappingAlias', $mapping);
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * 获取实体属性列表.
     *
     * @return array
     */
    private function getEntityProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[] = $property->getName();
        }
        return $properties;
    }

    /**
     * 获取包含映射关系的实体属性列表.
     *
     * @return array
     */
    private function getEntityPropertiesMap(): array
    {
        $properties = $this->getOption('view_properties');
        if (empty($properties)) {
            $fields     = $this->getEntityProperties();
            $mapping    = $this->getOption('viewMapping', []);
            $properties = [];
            foreach ($fields as $field) {
                if (isset($mapping[$field])) {
                    $properties[$field] = $mapping[$field];
                } else {
                    $properties[] = $field;
                }
            }

            $this->setOption('view_properties', $properties);
        }

        return $properties;
    }

    /**
     * 转换为数组. 视图模型不支持 hidden visible append
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->getData();
        foreach ($data as $name => &$val) {
            if ($val instanceof Modelable || $val instanceof Collection) {
                $val = $val->toArray();
            }
        }
        return $data;
    }

    /**
     * 设置视图模型数据
     *
     * @param array $data 数据
     * @return $this
     */
    public function data(array $data)
    {
        // 数据验证
        $data = $this->validate($data);
        foreach ($this->getEntityProperties() as $field) {
            $this->$field = $data[$field] ?? null;
        }
        return $this;
    }

    /**
     * 刷新模型数据.
     *
     * @return $this
     */
    public function refresh()
    {
        if ($this->isEmpty()) {
            $this->initData();
        }
        return $this;
    }

    /**
     * 清空视图模型数据
     *
     * @return $this
     */
    public function clear()
    {
        foreach ($this->getEntityProperties() as $field) {
            $this->$field = null;
        }
        return $this;
    }

    /**
     * 获取视图模型数据
     *
     * @return array
     */
    protected function getData(): array
    {
        $data = [];
        foreach ($this->getEntityProperties() as $field) {
            $data[$field] = $this->$field;
        }
        return $data;
    }

    /**
     * 设置需要附加的输出属性.（视图模型下无效）
     *
     * @param array $append 属性列表
     *
     * @return $this
     */
    public function append(array $append)
    {
        return $this;
    }

    /**
     * 设置需要隐藏的输出属性.（视图模型下无效）
     *
     * @param array $hidden 属性列表
     *
     * @return $this
     */
    public function hidden(array $hidden)
    {
        return $this;
    }

    /**
     * 设置需要输出的属性.（视图模型下无效）
     *
     * @param array $visible
     *
     * @return $this
     */
    public function visible(array $visible)
    {
        return $this;
    }

    /**
     * 判断数据是否为空.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->model()->isEmpty();
    }

    /**
     * 获取克隆的模型实例.
     *
     * @return static
     */
    public function clone()
    {
        $model = new static();
        $model->setModel($this->model())->initData();
        return $model;
    }

    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * 设置关联数据.
     *
     * @param string $relation 关联属性
     * @param Model  $model  关联数据
     *
     * @return void
     */
    public function setRelation($relation, $model)
    {
        $this->model()->setRelation($relation, $model);
    }

    /**
     * 设置关联绑定数据
     *
     * @param Model  $model 关联对象
     * @param array  $bind  绑定属性
     * @param string $relation  关联名称
     * @return void
     */
    public function bindRelationAttr($model, $bind, $relation) 
    {
        if ($relation) {
            $this->setRelation($relation, $model);
        }
    }

    /**
     * 视图模型数据转换为模型数据（用于写入）.
     *
     * @return array
     */
    protected function convertData(): array
    {
        // 获取实体属性
        $properties = $this->getEntityPropertiesMap();
        $mapping    = $this->getOption('autoMappingAlias', []);
        $data       = $this->getData();
        $item       = [];
        $together   = [];
        foreach ($properties as $key => $field) {
            if (strpos($field, '->')) {
                $fields     = explode('->', $field);
                $together[] = current($fields);
                $last       = array_pop($fields);
                $target     = $this->model();
                foreach ($fields as $attr) {
                    $target = $target?->$attr;
                }
                if ($target) {
                    $target->$last = $data[$key];
                }
            } elseif (is_int($key) && isset($mapping[$field])) {
                [$relation] = explode('->', $mapping[$field]);
                $together[] = $relation;
                $this->model()->$relation->$field = $data[$field];
            } else {
                $item[$field] =  $data[is_int($key) ? $field : $key];
            }
        }

        if (!empty($together)) {
            // 自动关联写入
            $together = array_unique($together);
            $this->model()->together($together);
        }
        return $item;
    }

    /**
     * 验证视图模型数据. 
     *
     * @param array $data 数据
     * @param array $allow 需要验证的字段
     *
     * @throws ValidateException
     * @return array
     */
    protected function validate(array $data = [], array $allow = []): array
    {
        $validater = $this->getOption('validate');
        $data      = $data ?: $this->getData();
        if (!empty($validater)) {
            return validate($validater)
                ->only($allow ?: array_keys($data))
                ->checked($data);
        }
        return $data;
    }

    /**
     * 保存模型实例数据.
     *
     * @return bool
     */
    public function save(): bool
    {
        // 根据映射关系转换为实际模型数据
        $data = $this->convertData();
        // 处理自动时间字段数据
        foreach ($this->model()->getAutoTimeFields() as $field) {
            unset($data[$field]);
        }

        return $this->model()->save($data);
    }

    /**
     * 删除模型数据.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->model()->delete()) {
            $this->clear();
            return true;
        }

        return false;
    }

    /**
     * 写入数据.
     *
     * @param array|object  $data 数据
     * @return static
     */
    public static function create(array | object $data)
    {
        $entity = new static();
        $model  = $entity->model()->exists(false)->save($data, true);
        $entity->refresh();

        return $entity;
    }

    /**
     * 更新数据.
     *
     * @param array|object  $data 数据
     * @param mixed  $where       更新条件
     * @param array  $allowField  允许字段
     * @return static
     */
    public static function update(array | object $data, $where = [], array $allowField = [])
    {
        $entity = new static();
        $model  = $entity->model()->allowField($allowField)->exists(true)->save($data, $where, true);
        // 刷新视图模型数据
        $entity->refresh();

        return $entity;
    }

    /**
     * 获取属性 支持获取器
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name ?? null;
        }
        return $this->model()->$name;
    }

    /**
     * 设置数据 支持类型自动转换
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * 检测数据对象的值
     *
     * @param string $name 名称
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return !is_null($this->__get($name));
    }

    /**
     * 销毁数据对象的值
     *
     * @param string $name 名称
     *
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->$name);
    }

    public function __debugInfo()
    {
        return [];
    }

    /**
     * 克隆模型实例
     * 
     * @return void
     */
    public function __clone()
    {
    }

    /**
     * 序列化模型对象
     * 
     * @return array
     */
    public function __serialize(): array
    {
        return $this->getData();
    }

    /**
     * 反序列化模型对象
     * 
     * @param array $data 
     * @return void
     */
    public function __unserialize(array $data) 
    {
        foreach ($data as $name => $val) {
            $this->$name = $val;
        }
    }

    public static function __callStatic($method, $args)
    {
        $entity = new static();
        if (in_array($method, ['destroy', 'saveAll'])) {
            // 调用model的静态方法
            $db = $entity->model();
        } else {
            // 调用Query类查询方法
            $db = $entity->model()->db();
        }

        if ('with' != $method && !empty($entity->getOption('autoMapping'))) {
            // 自动关联查询
            $db->with($entity->getOption('autoMapping'));
        }

        return call_user_func_array([$db, $method], $args);
    }

    public function __call($method, $args)
    {
        // 调用Model类方法
        $result = call_user_func_array([$this->model(), $method], $args);
        return $result instanceof Model ? $this : $result;
    }
}
