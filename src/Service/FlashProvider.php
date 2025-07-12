<?php

declare(strict_types=1);

namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Slim\Flash\Messages as Flash;

class FlashProvider implements ServiceProviderInterface
{
    public function __construct(protected ?string $storageKey = null)
    {
    }

    public function register(Container $container)
    {
        $storageKey = $this->storageKey;

        $container['flash'] = static function (Container $c) use ($storageKey) {
            $storage = null; // $_SESSIONを使用する
            return new Flash($storage, $storageKey);
        };
    }
}
