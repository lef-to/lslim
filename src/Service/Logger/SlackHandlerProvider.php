<?php
declare(strict_types=1);
namespace LSlim\Service\Logger;

use Lefto\Monolog\Formatter\SlackFormatter;
use Lefto\Monolog\Handler\SlackHandler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;

class SlackHandlerProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string|int
     */
    private $level;

    /**
     * @var int
     */
    protected $retryCount;

    /**
     * @var callable|null
     */
    protected $retryDelay;

    /**
     * @var bool
     */
    protected $throwException;

    public function __construct(
        $name,
        $url,
        $level = Logger::ERROR,
        $retryCount = 0,
        callable $retryDelay = null,
        bool $throwException = false
    ) {
        $this->name = $name;
        $this->url = $url;
        $this->level = $level;
        $this->retryCount = $retryCount;
        $this->retryDelay = $retryDelay;
        $this->throwException  = $throwException;
    }

    public function register(Container $container)
    {
        $name = $this->name;
        $url = $this->url;
        $level = $this->level;
        $retryCount = $this->retryCount;
        $retryDelay = $this->retryDelay;
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
        $handler = new SlackHandler($url, $level, true, $retryCount, $retryDelay, $throwException);
        $formatter = new SlackFormatter($name);

        $handler->setFormatter($formatter);

        return $handler;
    }
}
