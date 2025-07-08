<?php

declare(strict_types=1);

namespace LSlim\Service\Logger;

use Monolog\Handler\SlackWebhookHandler;
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
        private $attachment = true,
        private $emoji = ':boom:',
        private $short = false,
        private $context = true,
        private $bubble = true,
        private $exclude_fields = []
    ) {
    }

    public function register(Container $container)
    {
        $container->extend(
            'logger',
            function (
                Logger $logger,
                Container $c
            ) {
                $handler = new SlackWebhookHandler(
                    $this->url,
                    null,
                    $this->name,
                    $this->attachment,
                    $this->emoji,
                    $this->short,
                    $this->context,
                    $this->level,
                    $this->bubble,
                    $this->exclude_fields
                );
                $logger->pushHandler($handler);
                return $logger;
            }
        );
    }
}
