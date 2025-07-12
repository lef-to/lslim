<?php

declare(strict_types=1);

namespace LSLim\Service;

use LSlim\Middleware\LoggerExtender;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class LoggerProvider implements ServiceProviderInterface
{
    public function __construct(private string $name, private array $config = [])
    {
    }

    public function register(Container $container)
    {
        $name =  $this->name;
        $config = $this->config;

        $container['logger'] = static function (Container $c) use ($name, $config) {
            $defaultLevel = ($c['env'] == 'production') ? Level::Info : Level::Debug;
            $level = $config['level'] ?? $defaultLevel;
            $permission = $config['permission'] ?? 0664;
            $lock = $config['use_lock'] ?? false;
            $rotate = $config['rotate'] ?? 30;
            $logDir = $config['dir'] ?? $c['log_dir'];
            $format = $config['format']
                ?? "[%datetime%] %level_name%: %message% %context% %extra%\n";

            $logger = new Logger($name);

            $path = $logDir . DIRECTORY_SEPARATOR . $name . '.log';
            $sapiName = php_sapi_name();
            if ($sapiName == 'cli' || $sapiName == 'cli-server') {
                $handler = new StreamHandler('php://stderr', $level);

                $format = "[%datetime%] %level_name%: %message% %context%\n";
                if (stream_isatty(STDERR)) {
                    $formatter = new ColoredLineFormatter(null, $format);
                } else {
                    $formatter = new LineFormatter($format);
                }
                $formatter->includeStacktraces(true);

                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);

                $path = $logDir . DIRECTORY_SEPARATOR . $name . '_cli.log';
            }

            $handler = new RotatingFileHandler($path, $rotate, $level, true, $permission, $lock);

            $formatter = new LineFormatter($format);
            $formatter->includeStacktraces(true);

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        };

        $container['logger_extender'] = static function (Container $c) use ($config) {
            $attr = $config['client_ip_attr'] ?? 'client-ip';
            return new LoggerExtender($c, $attr);
        };
    }
}
