<?php
declare(strict_types=1);
namespace LSlim\Service\Logger;

use LSlim\Monolog\Handler\SlackHandler;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;

class SlackHandlerProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string|int
     */
    private $level;

    /**
     * @param string $url
     * @param string|int $level
     */
    public function __construct($url, $level = Logger::ERROR)
    {
        $this->url = $url;
        $this->level = $level;
    }

    public function register(Container $container)
    {
        $url = $this->url;
        $level = $this->level;

        $container->extend(
            'logger',
            static function (Logger $logger, Container $c) use ($url, $level) {
                $handler = static::createHandler($url, $level);
                $logger->pushHandler($handler);
                return $logger;
            }
        );
    }

    protected static function createHandler($url, $level)
    {
        $handler = new SlackHandler($url, $level, true);
        return $handler;
    }
}
