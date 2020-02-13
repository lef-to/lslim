<?php
declare(strict_types=1);
namespace LSLim\Service;

use LSlim\Middleware\LoggerExtender;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class LoggerProvider implements ServiceProviderInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $config;

    public function __construct($name, array $config = [])
    {
        $this->name =  $name;
        $this->config = $config;
    }

    public function register(Container $container)
    {
        $name =  $this->name;
        $config = $this->config;

        $container['logger'] = static function (Container $c) use ($name, $config) {
            $defaultLevel = ($c['env'] == 'production') ? Logger::INFO : Logger::DEBUG;
            $level = $config['level'] ?? $defaultLevel;
            $permission = $config['permission'] ?? 0664;
            $lock = $config['use_lock'] ?? false;
            $introspectionEnabled = $config['introspection_enabled'] ?? false;
            $rotate = $config['rotate'] ?? 30;
            $logDir = $config['dir'] ?? $c['log_dir'];
            $format = $config['format']
                ?? "[%datetime%][%extra.client_ip%] \"%extra.http_method% %extra.request_path%\" %level_name%: %message% %context%\n";

            $logger = new Logger($name);

            if ($introspectionEnabled) {
                $introspectionLevel = $config['introspection_level']  ?? $defaultLevel;
                $processor = new IntrospectionProcessor($introspectionLevel);
                $logger->pushProcessor($processor);
            }

            $path = $logDir . DIRECTORY_SEPARATOR . $name . '.log';
            $sapiName = php_sapi_name();
            if ($sapiName == 'cli' || $sapiName == 'cli-server') {
                $handler = new StreamHandler('php://stderr', $level);

                $formatter = new LineFormatter("[%datetime%] %level_name%: %message% %context%\n");
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
