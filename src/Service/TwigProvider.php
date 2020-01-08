<?php
declare(strict_types=1);
namespace LSlim\Service;

use LSlim\Middleware\ViewExtender;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Slim\Views\Twig;
use LSlim\Twig\SessionExtension;
use Twig\Extension\DebugExtension;

class TwigProvider implements ServiceProviderInterface
{
    /**
     * @var string|array|null
     */
    protected $path;

    /**
     * @var array
     */
    protected $option;

    /**
     * @var string
     */
    protected $basePath = '';

    public function __construct($option = [], $path = null)
    {
        $this->path = $path;
        $this->option = $option;
    }

    public function setBasePath($basePath): self
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function register(Container $container)
    {
        $path = $this->path;
        $option = $this->option;
        $basePath = $this->basePath;

        $container['view'] = static function (Container $c) use ($path, $option) {
            if (!$path) {
                $path = rtrim($c['base_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'templates';
            }

            if (!isset($option['auto_reload'])) {
                $option['auto_reload'] = true;
            }

            if (!isset($option['cache']) && isset($c['cache_dir'])) {
                $option['cache'] = rtrim($c['cache_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'view';
            }

            if (!isset($option['debug']) && isset($c['env'])) {
                $option['debug'] = ($c['env'] != 'production') ? true : false;
            }

            $twig = Twig::create($path, $option);
            $twig->addExtension(new DebugExtension());
            if (session_status() === PHP_SESSION_ACTIVE) {
                $twig->addExtension(new SessionExtension());
            }

            $twig['container'] = $c;

            return $twig;
        };

        $container['view_extender'] = static function (Container $c) use ($basePath) {
            return new ViewExtender($c, $basePath);
        };
    }
}
