<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Illuminate\Container\Container as BaseContainer;
use Illuminate\Contracts\Foundation\Application as ApplicationInterface;
use Illuminate\Support\Str;
use Closure;
use BadMethodCallException;

class Container extends BaseContainer implements ApplicationInterface
{
    /**
     * @inheritdoc
     */
    public function basePath($path = '')
    {
        return $this['path.base'];
    }

    /**
     * @inheritdoc
     */
    public function bootstrapPath($path = '')
    {
        return $this->basePath() . DIRECTORY_SEPARATOR . 'bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * @inheritdoc
     */
    public function configPath($path = '')
    {
        return $this['path.config'];
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
    public function environmentPath()
    {
        return $this->basePath();
    }

    /**
     * @inheritdoc
     */
    public function environmentFile()
    {
        return '.env';
    }

    public function environmentFilePath()
    {
        return $this->environmentPath() . DIRECTORY_SEPARATOR . $this->environmentFile();
    }

    /**
     * @inheritdoc
     */
    public function environment(...$environments)
    {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;
            return Str::is($patterns, $this['env']);
        }
        return $this['env'];
    }

    /**
     * @inheritdoc
     */
    public function detectEnvironment(Closure $callback)
    {
        throw new BadMethodCallException('Method detectEnvironment is not implemented.');
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
    public function configurationIsCached()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCachedConfigPath()
    {
        throw new BadMethodCallException('Method getCachedConfigPath is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function routesAreCached()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCachedRoutesPath()
    {
        throw new BadMethodCallException('Method getCachedRoutesPath is not implemented.');
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
        return $this['env'] === 'testing';
    }

    /**
     * @inheritdoc
     */
    public function version()
    {
        throw new BadMethodCallException('Method version is not implemented.');
    }

    public function bootstrapWith(array $bootstrappers)
    {
        throw new BadMethodCallException('Method bootstrapWith is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);
        return parent::make($abstract, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function databasePath($path = '')
    {
        return $this['path.database'];
    }

    /**
     * @inheritdoc
     */
    public function getLocale()
    {
        throw new BadMethodCallException('Method getLocalle is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function setLocale($locale)
    {
        throw new BadMethodCallException('Method setLocale is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function isLocale($locale)
    {
        throw new BadMethodCallException('Method isLocale is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function getNamespace()
    {
        throw new BadMethodCallException('Method getNamespace is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function getProviders($provider)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasBeenBootstrapped()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function loadDeferredProviders()
    {
    }

    /**
     * @inheritdoc
     */
    public function loadEnvironmentFrom($file)
    {
        throw new BadMethodCallException('Method loadEnvironmentFrom is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function resolveProvider($provider)
    {
        throw new BadMethodCallException('Method resolveProvider is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function resourcePath($path = '')
    {
        return $this->basePath() . DIRECTORY_SEPARATOR . 'resources ' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * @inheritdoc
     */
    public function shouldSkipMiddleware()
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function storagePath()
    {
        return $this['path.storage'];
    }

    /**
     * @inheritdoc
     */
    public function terminate()
    {
        throw new BadMethodCallException('Method terminate is not implemented.');
    }
}
