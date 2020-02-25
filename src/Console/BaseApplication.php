<?php
declare(strict_types=1);
namespace LSlim\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Illuminate\Cache\Console\CacheTableCommand;
use Illuminate\Cache\Console\ClearCommand as CacheClearCommand;
use Illuminate\Cache\Console\ForgetCommand as CacheForgetCommand;
use Illuminate\Database\Migrations\DatabaseMigrationRepository as Repository;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\InstallCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\Migrations\StatusCommand;
use Illuminate\Queue\Console\FailedTableCommand;
use Illuminate\Queue\Console\FlushFailedCommand;
use Illuminate\Queue\Console\ForgetFailedCommand;
use Illuminate\Queue\Console\ListenCommand;
use Illuminate\Queue\Console\ListFailedCommand;
use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Queue\Console\RestartCommand;
use Illuminate\Queue\Console\TableCommand;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Listener;
use Illuminate\Queue\Worker;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use LSlim\Console\Command\Mail\TestCommand as MailTestCommand;
use LSlim\Console\Command\Session\TableCommand as SessionTableCommand;
use LSlim\Console\Command\Queue\SupervisorCommand;
use BadMethodCallException;
use Exception;

class BaseApplication extends Application implements ExceptionHandler
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * @var \LSlim\Illuminate\Container
     */
    protected $laravel;

    /**
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * @param string $appName
     * @param string $version
     * @param \Psr\Container\ContainerInterface $container
     */
    public function __construct($appName, $version, ContainerInterface $container)
    {
        parent::__construct($appName, $version);
        $this->container = $container;

        if ($container->has('laravel')) {
            $this->laravel = $this->container->get('laravel');

            $this->laravel->instance('files', new Filesystem());
            $this->composer = new Composer($this->laravel['files'], $this->laravel['path.base']);

            if ($this->laravel->bound('db')) {
                Schema::setFacadeApplication($this->laravel);
                DB::setFacadeApplication($this->laravel);

                $resolver = $this->laravel['db'];
                $repository = new Repository($resolver, 'migration');
                $migrator = new Migrator($repository, $resolver, $this->laravel['files']);

                $this->laravel->singleton('migration.creator', static function ($app) {
                    return new MigrationCreator($app['files']);
                });

                $this->add(new InstallCommand($repository));
                $this->add(new MigrateCommand($migrator));
                $this->add(new MigrateMakeCommand($this->laravel['migration.creator'], $this->composer));
                $this->add(new RefreshCommand());
                $this->add(new ResetCommand($migrator));
                $this->add(new RollbackCommand($migrator));
                $this->add(new StatusCommand($migrator));
            }

            if ($this->laravel->bound('cache')) {
                $this->add(new CacheTableCommand($this->laravel['files'], $this->composer));
                $this->add(new CacheClearCommand($this->laravel['cache'], $this->laravel['files']));
                $this->add(new CacheForgetCommand($this->laravel['cache']));
            }

            if ($this->laravel->bound('queue')) {
                $trace = debug_backtrace();
                $trace = end($trace);
                $file = $trace['file'];
                $this->laravel->singleton('queue.listener', static function ($app) use ($file) {
                    $dir = dirname($file);

                    if (!defined('ARTISAN_BINARY')) {
                        $name = basename($file);
                        define('ARTISAN_BINARY', $name);
                    }
                    return new Listener($dir);
                });

                $this->laravel->singleton(EventDispatcherContract::class, static function ($app) {
                    $dispatcher = new EventDispatcher($app);
                    return $dispatcher->setQueueResolver(function () use ($app) {
                        return $app['queue'];
                    });
                });
                $this->laravel->alias(EventDispatcherContract::class, 'events');

                $this->laravel->singleton('queue.worker', function ($app) {
                    return new Worker(
                        $app['queue'],
                        $app['events'],
                        $this,
                        function () use ($app) {
                            return $app->isDownForMaintenance();
                        }
                    );
                });

                $this->laravel->singleton('queue.failer', static function ($app) {
                    $failed = $app['config']['queue.failed'];
                    return isset($failed['table'])
                        ? new DatabaseFailedJobProvider($app['db'], $failed['database'] ?? 'default', $failed['table'])
                        : new NullFailedJobProvider();
                });

                $this->laravel->singleton(BusDispatcher::class, function ($app) {
                    return new BusDispatcher($app, function ($connection = null) use ($app) {
                        return $app[QueueFactoryContract::class]->connection($connection);
                    });
                });

                $this->laravel->alias(BusDispatcher::class, BusDispatcherContract::class);

                $this->laravel->alias(BusDispatcher::class, QueueingDispatcherContract::class);

                Queue::setFacadeApplication($this->laravel);

                if ($container->has('db')) {
                    $this->add(new FailedTableCommand($this->laravel['files'], $this->composer));
                    $this->add(new FlushFailedCommand());
                    $this->add(new ForgetFailedCommand());
                    $this->add(new TableCommand($this->laravel['files'], $this->composer));
                }

                $this->add(new ListenCommand($this->laravel['queue.listener']));
                $this->add(new ListFailedCommand());
                $this->add(new RetryCommand());

                if ($this->laravel->bound('cache')) {
                    $this->add(new RestartCommand($this->laravel['cache']->store()));
                    $this->add(new WorkCommand($this->laravel['queue.worker'], $this->laravel['cache']->store()));
                }

                $this->add(new SupervisorCommand($appName, $file, $container));
            }
        }

        if ($container->has('mailer')) {
            $this->add(new MailTestCommand($this->container, $appName));
        }

        if ($container->has('db')) {
            $this->add(new SessionTableCommand(
                $this->laravel['migration.creator'],
                $this->laravel['files'],
                $this->composer
            ));
        }
    }

    /**
     * @inhericdoc
     */
    public function add(Command $command)
    {
        if ($command instanceof IlluminateCommand) {
            $command->setLaravel($this->laravel);
        }

        parent::add($command);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultInputDefinition()
    {
        $message = 'The environment the command should run under';
        $option = new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);

        $definition = parent::getDefaultInputDefinition();
        $definition->addOption($option);

        return $definition;
    }

    /**
     * @inheritdoc
     */
    public function report(Exception $ex)
    {
        $logger = $this->container->get('logger');
        $logger->error($ex->getMessage(), [ 'exception' => $ex ]);
    }

    /**
     * @inheritdoc
     */
    public function shouldReport(Exception $e)
    {
        return true;
    }

    /**
     * @inheritdoc
     * @phan-suppress PhanUndeclaredTypeReturnType
     */
    public function render($request, Exception $e)
    {
        throw new BadMethodCallException('Method render is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function renderForConsole($output, Exception $e)
    {
        $this->renderThrowable($e, $output);
    }
}
