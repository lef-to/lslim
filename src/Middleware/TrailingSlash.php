<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Middlewares\TrailingSlash as BaseMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrailingSlash extends BaseMiddleware
{
    /**
     * @var string
     */
    protected $basePath;

    public function __construct($trailingSlash = false, $basePath = '')
    {
        parent::__construct($trailingSlash);
        $this->basePath = '/' . trim($basePath, '/') . '/';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path === $this->basePath) {
            return $handler->handle($request);
        }

        return parent::process($request, $handler);
    }
}
