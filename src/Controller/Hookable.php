<?php
declare(strict_types=1);
namespace LSlim\Controller;

use Psr\Http\Message\ResponseInterface;
use BadMethodCallException;

trait Hookable
{
    public function __call($name, array $arguments)
    {
        $methodName = 'action' . ucfirst($name);
        if (method_exists($this, $methodName)) {
            $request = $arguments[0];
            $response = $arguments[1];
            $args = $arguments[2] ?? [];

            if (method_exists($this, 'beforeAction')) {
                $res = $this->beforeAction($name, $request, $response, $args);
                if ($res instanceof ResponseInterface) {
                    return $res;
                }
            }

            $response = $this->$methodName($request, $response, $args);

            if (method_exists($this, 'afterAction')) {
                $response = $this->afterAction($name, $request, $response, $args);
            }

            return $response;
        }

        throw new BadMethodCallException($methodName . ' method is not exists.');
    }
}
