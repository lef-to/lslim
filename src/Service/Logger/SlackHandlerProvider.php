<?php
declare(strict_types=1);
namespace LSlim\Service\Logger;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;
use Monolog\Handler\SlackWebhookHandler;

class SlackHandlerProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var bool
     */
    private $useAttachment;

    /**
     * @var bool
     */
    private $useShortAttachment;

    /**
     * @var bool
     */
    private $includeContextAndExtra;

    /**
     * @var string|int
     */
    private $level;

    /**
     * @var array
     */
    private $excludeFields;

    /**
     * @param string $url
     * @param bool $useAttachment
     * @param bool $useShortAttachment
     * @param bool $includeContextAndExtra
     * @param string|int $level
     * @param array $excludeFields
     */
    public function __construct(
        $url,
        $useAttachment = true,
        $useShortAttachment = false,
        $includeContextAndExtra = false,
        $level = Logger::ERROR,
        $excludeFields = []
    ) {
        $this->url = $url;
        $this->useAttachment = $useAttachment;
        $this->useShortAttachment = $useShortAttachment;
        $this->includeContextAndExtra = $includeContextAndExtra;
        $this->level = $level;
        $this->excludeFields = $excludeFields;
    }

    public function register(Container $container)
    {
        $url = $this->url;
        $useAttachment = $this->useAttachment;
        $useShortAttachment = $this->useShortAttachment;
        $includeContextAndExtra = $this->includeContextAndExtra;
        $level = $this->level;
        $excludeFields = $this->excludeFields;

        $container->extend(
            'logger',
            static function (
                Logger $logger,
                Container $c
            ) use (
                $url,
                $useAttachment,
                $useShortAttachment,
                $includeContextAndExtra,
                $level,
                $excludeFields
            ) {
                $handler = static::createHandler(
                    $url,
                    $useAttachment,
                    $useShortAttachment,
                    $includeContextAndExtra,
                    $level,
                    $excludeFields
                );

                $logger->pushHandler($handler);
                return $logger;
            }
        );
    }

    protected static function createHandler(
        $url,
        $useAttachment,
        $useShortAttachment,
        $includeContextAndExtra,
        $level,
        $excludeFields
    ) {
        return new SlackWebhookHandler(
            $url,
            null,
            null,
            $useAttachment,
            null,
            $useShortAttachment,
            $includeContextAndExtra,
            $level,
            true,
            $excludeFields
        );
    }
}
