<?php

namespace LSlim\Logger;

use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

class LazyLogger extends AbstractLogger
{
    private ?LoggerInterface $logger;

    public function __construct(private ContainerInterface $container, private string $key = 'logger')
    {
        $this->logger = null;
    }

    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = $this->container->get($this->key);
        }
        return $this->logger;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }
}
