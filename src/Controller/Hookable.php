<?php
declare(strict_types=1);
namespace LSlim\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use BadMethodCallException;

trait Hookable
{
    abstract protected function handleAction(
        $name,
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
        callable $action
    ): ResponseInterface;

    public function __call($name, array $arguments)
    {
        $methodName = 'action' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            return $this->handleAction(
                $name,
                $arguments[0],
                $arguments[1],
                $arguments[2],
                [ $this, $methodName ]
            );
        }

        throw new BadMethodCallException($methodName . ' method is not exists.');
    }
}
