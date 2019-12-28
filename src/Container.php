<?php
declare(strict_types=1);
namespace LSlim;

use Psr\Container\ContainerInterface;
use Pimple\Container as PImpleContainer;

class Container extends PImpleContainer implements ContainerInterface
{
    public function __construct($env, $values = [])
    {
        parent::__construct($values);
        $this->offsetSet('env', $env);
    }

    public function get($id)
    {
        return $this->offsetGet($id);
    }

    public function has($id)
    {
        return $this->offsetExists($id);
    }
}
