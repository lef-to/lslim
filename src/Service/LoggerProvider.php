<?php
declare(strict_types=1);
namespace LSLim\Service;

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
            $introspectionLevel = $config['introspection_level']  ?? $defaultLevel;
            $rotate = $config['rotate'] ?? 10;
            $logDir = $config['dir'] ?? $c['log_dir'];

            $logger  = new Logger($name);
            $processor = new IntrospectionProcessor($introspectionLevel);
            $logger->pushProcessor($processor);

            $name = $logDir . DIRECTORY_SEPARATOR . $name . '.log';
            $sapiName = php_sapi_name();
            if ($sapiName == 'cli' || $sapiName == 'cli-server') {
                $handler = new StreamHandler('php://stderr', $level);

                $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n");
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);

                $name = $logDir . DIRECTORY_SEPARATOR . $name . '_cli.log';
            }

            $handler = new RotatingFileHandler($name, $rotate, $level, true, $permission, $lock);

            $formatter = new LineFormatter();
            $formatter->includeStacktraces(true);

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        };
    }
}
