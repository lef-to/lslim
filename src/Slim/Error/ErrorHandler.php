<?php
declare(strict_types=1);
namespace LSlim\Slim\Error;

use Slim\Handlers\ErrorHandler as BaseHandler;
use Slim\Interfaces\CallableResolverInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Container\ContainerInterface;
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
        ContainerInterface $container
    ) {
        parent::__construct($callableResolver, $responseFactory);
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    protected function logError(string $error): void
    {
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = $this->container->get('logger');

        if ($this->exception instanceof HttpNotFoundException) {
            $level = LogLevel::WARNING;
        } else {
            $level = LogLevel::CRITICAL;
        }
        $logger->log($level, $error);
    }
}
