<?php
declare(strict_types=1);
namespace LSlim\Slim;

use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Slim\Csrf\Guard as Csrf;
use Slim\Flash\Messages as Flash;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Formatter\LineFormatter;
use Illuminate\Database\Capsule\Manager as Database;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Intervention\Image\ImageManager;
use LSlim\Twig\LSlimExtension;
use LSlim\Validation\Validator;
use LSlim\Mail\Mailer;
use LSlim\Middleware\Pagination;

class ContainerFactory
{
    private static function makeConfigPath(Container $c, $name)
    {
        return $c->get('config_dir')
            . DIRECTORY_SEPARATOR
            . $c->get('app_mode')
            . DIRECTORY_SEPARATOR
            . $name;
    }

    public static function create($appName, $appMode, $configDir): Container
    {
        $config = require $configDir . DIRECTORY_SEPARATOR . $appMode . DIRECTORY_SEPARATOR . 'container.php';
        $config['app_mode'] = $appMode;

        $container = new Container($config);

        if (!$container->has('config_dir')) {
            $container['config_dir'] = rtrim($configDir, DIRECTORY_SEPARATOR);
        }

        if (!$container->has('app_dir')) {
            $container['app_dir'] = function (Container $c) {
                return realpath($c->get('config_dir') . DIRECTORY_SEPARATOR . '..');
            };
        }

        if (!$container->has('var_dir')) {
            $container['var_dir'] = function (Container $c) {
                return rtrim($c->get('app_dir'), DIRECTORY_SEPARATOR) . '/var';
            };
        }

        if (!$container->has('tmp_dir')) {
            $container['tmp_dir'] = function (Container $c) {
                return rtrim($c->get('var_dir'), DIRECTORY_SEPARATOR) . '/tmp';
            };
        }

        if (!$container->has('cache_dir')) {
            $container['cache_dir'] = function (Container $c) {
                return rtrim($c->get('var_dir'), DIRECTORY_SEPARATOR) . '/cache';
            };
        }

        if (!$container->has('log_dir')) {
            $container['log_dir'] = function (Container $c) {
                return rtrim($c->get('var_dir'), DIRECTORY_SEPARATOR) . '/log';
            };
        }

        if (!$container->has('data_dir')) {
            $container['data_dir'] = function (Container $c) {
                return rtrim($c->get('var_dir'), DIRECTORY_SEPARATOR) . '/data';
            };
        }

        $container['logger'] = function (Container $c) use ($appName) {
            $logger  = new Logger($appName);
            $level = $c->has('log_level') ? $c->get('log_level') : Logger::DEBUG;
            $introspectionLevel = $c->has('log_introspection_level')
                ? $c->has('log_introspection_level')
                : Logger::DEBUG;

            $processor = new IntrospectionProcessor($introspectionLevel);
            $logger->pushProcessor($processor);

            $sapiName = php_sapi_name();
            if ($sapiName == 'cli' || $sapiName == 'cli-server') {
                $handler = new StreamHandler('php://stderr', $level);

                $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n");
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);
            }

            $name = $c->get('log_dir') . DIRECTORY_SEPARATOR . $appName . '.log';
            $rotate = $c->has('log_rotate') ? $c->get('log_rorate') : 10;
            $handler = new RotatingFileHandler($name, $rotate, $level);

            $formatter = new LineFormatter();
            $formatter->includeStacktraces(true);

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        };

        $container['view'] = function (Container $c) {
            $options = [
                'auto_reload' => true,
                'cache' => $c->get('cache_dir'),
                'debug' => ($c->get('app_mode') != 'production') ? true : false
            ];

            $path = $c->get('app_dir') . DIRECTORY_SEPARATOR . 'templates';
            $view = new Twig($path, $options);

            $view->addExtension(new TwigDebugExtension());

            $router = $c->get('router');
            $uri = $c->get('request')->getUri();

            $view->addExtension(new TwigExtension($router, $uri));
            $view->addExtension(new LSlimExtension($c));

            return $view;
        };

        $container['csrf'] = function (Container $c) {
            $csrf = new Csrf();
            $csrf->setStorageLimit($c->has('csrf_limit') ? $c->get('csrf_limit') : 10);
            $csrf->setFailureCallable(function ($req, $res, $next) use ($c) {
                $uri = $req->getUri();
                if ($c->has('logger')) {
                    $c->get('logger')->error(
                        'csrf check failuer.',
                        [ 'path' => $uri->getPath() ]
                    );
                }
                return $res->withRedirect((string)$uri);
            });
            return $csrf;
        };

        $container['flash'] = function (Container $c) {
            return new Flash();
        };

        $container['db'] = function (Container $c) {
            $db = new Database();
            $config = require static::makeConfigPath($c, 'database.php');

            foreach ($config as $k => $v) {
                $db->addConnection($v, $k);
            }

            return $db;
        };

        $container['validator'] = function (Container $c) {
            return new Validator($c->get('logger'));
        };

        $container['mailer'] = function (Container $c) {
            Mailer::init($c->get('tmp_dir'));

            $config = require static::makeConfigPath($c, 'mailer.php');
            return new Mailer($config, $c->get('logger'));
        };

        $container['paginator'] = function (Container $c) {
            return new Pagination($c);
        };

        $container['image_manager'] = function (Container $c) {
            return new ImageManager([
                'cache' => [
                    'path' => rtrim($c->get('cache_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'image_manager'
                ]
            ]);
        };

        return $container;
    }
}
