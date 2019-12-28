<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use LSlim\Mail\MailerFactory;

class MailerFactoryProvider implements ServiceProviderInterface
{
    /**
     * @var array|null
     */
    protected $config;

    /**
     * @param array|null $config
     */
    public function __construct($config = null)
    {
        $this->config = $config;
    }

    public function register(Container $container)
    {
        $config = $this->config;
        $container['mailer_factory'] = static function (Container $c) use ($config) {
            if ($config === null) {
                $path = rtrim($c['config_dir'], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . trim($c['env'], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . 'mailer.php';
                $config = require($path);
            }
            return new MailerFactory($config, $c['logger'] ?? null);
        };
    }
}
