<?php
declare(strict_types=1);
namespace LSlim\Console;

use Psr\Container\ContainerInterface;
use Illuminate\Container\Container as BaseContainer;
use Illuminate\Contracts\Foundation\Application as ApplicationInterface;
use BadMethodCallException;

class Container extends BaseContainer implements ApplicationInterface
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $version;

    public function __construct(ContainerInterface $container, $version)
    {
        $this->container = $container;
        $this->version = $version;
    }

    /**
     * @inheritdoc
     */
    public function basePath()
    {
        return $this->container->get('app_dir');
    }

    /**
     * @inheritdoc
     */
    public function boot()
    {
        throw new BadMethodCallException('Method boot is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function booting($callback)
    {
        throw new BadMethodCallException('Method booting is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function booted($callback)
    {
        throw new BadMethodCallException('Method booted is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function environment()
    {
        return $this->container->get('app_mode');
    }

    /**
     * @inheritdoc
     */
    public function getCachedPackagesPath()
    {
        throw new BadMethodCallException('Method getCachedPackagesPath is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function getCachedServicesPath()
    {
        throw new BadMethodCallException('Method getCachedServicesPath is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function isDownForMaintenance()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function register($provider, $options = [], $force = false)
    {
        throw new BadMethodCallException('Method register is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function registerConfiguredProviders()
    {
        throw new BadMethodCallException('Method registerConfiguredProviders is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function registerDeferredProvider($provider, $service = null)
    {
        throw new BadMethodCallException('Method registerDeferredProvider is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function runningInConsole()
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * @inheritdoc
     */
    public function runningUnitTests()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function version()
    {
        return $this->version;
    }

    /**
     * Resolve the given type from the container.
     *
     * (Overriding Container::make)
     *
     * @param string $abstract
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);
        return parent::make($abstract, $parameters);
    }
}
