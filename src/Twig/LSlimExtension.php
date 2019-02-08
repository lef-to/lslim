<?php
declare(strict_types=1);
namespace LSlim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Psr\Container\ContainerInterface;

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
            new TwigFunction('error', [ $this, 'getError' ])
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
     */
    public function urlFor($name, array $data = [], array $queryParams = [])
    {
        $router = $this->container->get('router');
        $request = $this->container->get('request');

        if ($this->container->has('url_force_https') && $this->container->get('url_force_https')) {
            return $request->getUri()
                ->withUserInfo('', '')
                ->withScheme('https')
                ->withPort(443)->getBaseUrl() . $router->relativePathFor($name, $data, $queryParams);
        }

        return $request->getUri()
            ->withUserInfo('', '')
            ->getBaseUrl() . $router->relativePathFor($name, $data, $queryParams);
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
}
