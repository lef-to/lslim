<?php
declare(strict_types=1);
namespace LSlim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Psr\Container\ContainerInterface;
use Slim\Http\Uri;
use LSlim\Util\Request as RequestUtil;

class LSlimExtension extends AbstractExtension
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @param \Psr\Container\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('container', [ $this, 'container' ]),
            new TwigFunction('session', [ $this, 'session' ]),
            new TwigFunction('url_for', [ $this, 'urlFor' ]),
            new TwigFunction('has_errors', [ $this, 'hasErrors' ]),
            new TwigFunction('has_error', [ $this, 'hasError' ]),
            new TwigFunction('errors', [ $this, 'getErrors' ]),
            new TwigFunction('error', [ $this, 'getError' ]),
            new TwigFunction('has_flash', [ $this, 'hasFlashMessage' ]),
            new TwigFunction('flash', [ $this, 'getFlashMessage' ]),
        ];
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function container($key, $default = null)
    {
        if ($this->container->has($key)) {
            return $this->container->get($key);
        }
        return $default;
    }

    /**
     * @param string $name
     * @param array $data
     * @param array $queryParams
     * @return string
     */
    public function urlFor($name, array $data = [], array $queryParams = [])
    {
        $request = $this->container->get('request');
        $router = $this->container->get('router');
        $path = $router->relativePathFor($name, $data, $queryParams);

        if ($this->container->has('base_url')) {
            return rtrim($this->container->get('base_url'), '/') . $path;
        }

        $uri = RequestUtil::makeCurrentUri($request)
            ->withQuery('')
            ->withFragment('');

        if ($uri instanceof Uri) {
            return $uri->getBaseUrl() . $path;
        }

        return rtrim((string)$uri->withPath('/'), '/') . $path;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function session($key, $default = null)
    {
        if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION[$key])) {
            return $_SESSION[$key];
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        $validator = $this->container->get('validator');
        return $validator->hasErrors();
    }

    /**
     * @param string $name parameter name
     * @param string $key rule id
     * @return bool
     */
    public function hasError($name, $key = null)
    {
        $validator = $this->container->get('validator');
        return $validator->hasError($name, $key);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        $validator = $this->container->get('validator');
        return $validator->getErrors();
    }

    /**
     * @param string $name parameter name
     * @return array
     */
    public function getError($name)
    {
        $validator = $this->container->get('validator');
        return $validator->getError($name);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasFlashMessage($key)
    {
        $flash = $this->container->get('flash');
        return $flash->hasMessage($key);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getFlashMessage($key, $default = null)
    {
        $flash = $this->container->get('flash');
        return $flash->getFirstMessage($key, $default);
    }
}
