<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Illuminate\Support\Fluent;
use Illuminate\Support\Arr;

class Config extends Fluent
{
    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset): bool
    {
        return Arr::has($this->attributes, $offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value): void
    {
        Arr::set($this->attributes, $offset, $value);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset): void
    {
        Arr::set($this->attributes, $offset, null);
    }

    /**
     * @inheritdoc
     */
    public function __call($method, $parameters)
    {
        Arr::set($this->attributes, $method, count($parameters) > 0 ? $parameters[0] : true);

        return $this;
    }
}
