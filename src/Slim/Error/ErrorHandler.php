<?php

declare(strict_types=1);

namespace LSlim\Slim\Error;

use LSlim\Logger\LazyLogger;
use Slim\Handlers\ErrorHandler as BaseHandler;
use Slim\Interfaces\CallableResolverInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\Exception\HttpNotFoundException;

class ErrorHandler extends BaseHandler
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param CallableResolverInterface $callableResolver
     * @param ResponseFactoryInterface  $responseFactory
     * @param ContainerInterface $container
     */
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ContainerInterface $container,
        ?LoggerInterface $logger = null,
    ) {
        $this->container = $container;
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    protected function getDefaultLogger(): LoggerInterface
    {
        return new LazyLogger($this->container);
    }

    /**
     * @inheritdoc
     */
    protected function logError(string $error): void
    {
        if ($this->exception instanceof HttpNotFoundException) {
            $level = LogLevel::WARNING;
        } else {
            $level = LogLevel::CRITICAL;
        }
        $this->logger->log($level, $error);
    }
}
