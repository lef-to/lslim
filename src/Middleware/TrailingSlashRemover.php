<?php
declare(strict_types=1);
namespace LSlim\Middleware;

use Middlewares\TrailingSlash as BaseMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrailingSlashRemover extends BaseMiddleware
{
    /**
     * @var string
     */
    protected $basePath;

    public function __construct($basePath = null)
    {
        parent::__construct(false);
        $this->basePath = $basePath;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->basePath !== null) {
            $path = $request->getUri()->getPath();
            $basePath = '/' . trim($this->basePath, '/') . '/';
            if ($path === $basePath) {
                return $handler->handle($request);
            }
        }

        return parent::process($request, $handler);
    }
}
