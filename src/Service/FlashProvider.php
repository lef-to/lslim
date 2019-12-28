<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Slim\Flash\Messages as Flash;

class FlashProvider implements ServiceProviderInterface
{
    /**
     * @var string|null
     */
    protected $storageKey;

    public function __construct($storageKey = null)
    {
        $this->storageKey = $storageKey;
    }

    public function register(Container $container)
    {
        $storageKey = $this->storageKey;

        $container['flash'] = static function (Container $c) use ($storageKey) {
            $storage = null;
            return new Flash($storage, $storageKey);
        };
    }
}
