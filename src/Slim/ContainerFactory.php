<?php
declare(strict_types=1);
namespace LSlim\Slim;

use Slim\Container;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Slim\Csrf\Guard as Csrf;
use Slim\Flash\Messages as Flash;
use Psr\Http\Message\ServerRequestInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Formatter\LineFormatter;
use Illuminate\Database\Capsule\Manager as Database;
use Twig\Extension\DebugExtension as TwigDebugExtension;
use Intervention\Image\ImageManager;
use LSlim\Illuminate\Container as IlluminateContainer;
use LSlim\Illuminate\QueueFactory;
use LSlim\Twig\LSlimExtension;
use LSlim\Validation\Validator;
use LSlim\Mail\Mailer;
use LSlim\Middleware\Pagination;
use LSlim\Util\Request as RequestUtil;

class ContainerFactory
{
    public static function makeConfigPath(Container $c, $name)
    {
        return $c->get('config_dir')
            . DIRECTORY_SEPARATOR
            . $c->get('app_mode')
            . DIRECTORY_SEPARATOR
            . $name;
    }

    public static function create($appName, $appMode, $configDir): Container
    {
        $config = $configDir . DIRECTORY_SEPARATOR . $appMode . DIRECTORY_SEPARATOR . 'container.php';
        $config = (is_file($config)) ? include $config : [];
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

        $container->extend('request', function (ServerRequestInterface $request, Container $c) {
            $uri1 = $request->getUri();
            $uri2 = RequestUtil::makeCurrentUri($request);

            if ($uri1->getScheme() != $uri2->getScheme() ||
                $uri1->getPort() != $uri2->getPort()) {
                return $request->withUri($uri2);
            }
            return $request;
        });

        $container['logger'] = function (Container $c) use ($appName) {
            $logger  = new Logger($appName);
            $level = $c->has('log_level') ? $c->get('log_level') : Logger::DEBUG;
            $permission = $c->has('log_permission') ? $c->get('log_permission') : null;
            $lock = $c->has('log_use_lock') ? $c->has('log_use_lock') : false;

            $introspectionLevel = $c->has('log_introspection_level')
                ? $c->has('log_introspection_level')
                : Logger::DEBUG;

            $processor = new IntrospectionProcessor($introspectionLevel);
            $logger->pushProcessor($processor);

            $name = $c->get('log_dir') . DIRECTORY_SEPARATOR . $appName . '.log';
            $sapiName = php_sapi_name();
            if ($sapiName == 'cli' || $sapiName == 'cli-server') {
                $handler = new StreamHandler('php://stderr', $level);

                $formatter = new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n");
                $handler->setFormatter($formatter);
                $logger->pushHandler($handler);
            
                $name = $c->get('log_dir') . DIRECTORY_SEPARATOR . $appName . '_cli.log';
            }

            $rotate = $c->has('log_rotate') ? $c->get('log_rorate') : 10;
            $handler = new RotatingFileHandler($name, $rotate, $level, true, $permission, $lock);

            $formatter = new LineFormatter();
            $formatter->includeStacktraces(true);

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            return $logger;
        };

        $container['view'] = function (Container $c) {
            $options = [
                'auto_reload' => true,
                'cache' => rtrim($c->get('cache_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'view',
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
                $uri = RequestUtil::makeCurrentUri($req);
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
                    'path' => rtrim($c->get('cache_dir'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'image'
                ]
            ]);
        };

        return $container;
    }

    public static function provideIlluminateContainer(Container $container)
    {
        if (!$container->has('laravel')) {
            $container['laravel'] = function (Container $c) {
                return IlluminateContainer::create($c);
            };
        }
    }

    public static function provideDatabase(Container $container)
    {
        if (!$container->has('db')) {
            static::provideIlluminateContainer($container);

            $container['db'] = function (Container $c) {
                $db = new Database($c->get('laravel'));

                $path = static::makeConfigPath($c, 'database.php');
                $config = require $path;

                foreach ($config as $k => $v) {
                    $db->addConnection($v, $k);
                }

                return $db;
            };
        }
    }

    public static function provideQueue(Container $container)
    {
        if (!$container->has('queue')) {
            static::provideIlluminateContainer($container);

            $container['queue'] = function (Container $c) {
                $path = static::makeConfigPath($c, 'queue.php');
                return QueueFactory::create($c, $path);
            };
        }
    }
}
