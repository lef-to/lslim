<?php
declare(strict_types=1);
namespace LSlim\Service\Logger;

use Lefto\Monolog\Formatter\SlackFormatter;
use Lefto\Monolog\Handler\SlackHandler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;
use Monolog\Level;

class SlackHandlerProvider implements ServiceProviderInterface
{
    public function __construct(
        private $name,
        private $url,
        private $level = Level::Error,
        protected $retryCount = 0,
        protected ?callable $retryDelay = null,
        protected bool $throwException = false
    ) {
    }

    public function register(Container $container)
    {
        $name           = $this->name;
        $url            = $this->url;
        $level          = $this->level;
        $retryCount     = $this->retryCount;
        $retryDelay     = $this->retryDelay;
        $throwException = $this->throwException;

        $container->extend(
            'logger',
            static function (
                Logger $logger,
                Container $c
            ) use (
                $name,
                $url,
                $level,
                $retryCount,
                $retryDelay,
                $throwException
            ) {
                $handler = static::createHandler($name, $url, $level, $retryCount, $retryDelay, $throwException);
                $logger->pushHandler($handler);
                return $logger;
            }
        );
    }

    protected static function createHandler($name, $url, $level, $retryCount, $retryDelay, $throwException)
    {
        $handler    = new SlackHandler($url, $level, true, $retryCount, $retryDelay, $throwException);
        $formatter  = new SlackFormatter($name);

        $handler->setFormatter($formatter);

        return $handler;
    }
}
