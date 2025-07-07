<?php
declare(strict_types=1);
namespace LSlim\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use LSlim\Mail\MailerFactory;

class MailerProvider implements ServiceProviderInterface
{
    /**
     * @param array|null $config
     */
    public function __construct(protected ?array $config = null)
    {
    }

    public function register(Container $container)
    {
        $config = $this->config;
        $container['mailer'] = static function (Container $c) use ($config) {
            if ($config === null) {
                $path = rtrim($c['config_dir'], DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . 'mailer.php';
                $config = require($path);
            }
            return new MailerFactory($config, $c['logger'] ?? null);
        };
    }
}
