<?php
declare(strict_types=1);
namespace LSlim\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
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
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\Dispatcher;
use LSlim\Slim\ContainerFactory;
use LSlim\Illuminate\CacheFactory;
use LSlim\Console\Command\Mail\TestCommand as MailTestCommand;
use LSlim\Console\Command\Session\DatabaseInitCommand as SessionDatabaseInitCommand;
use LSlim\Console\Command\Queue\SupervisorCommand;
use BadMethodCallException;
use Exception;

class Application extends BaseApplication implements ExceptionHandler
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var \LSlim\Illuminate\Container
     */
    private $laravel;

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
            $this->laravel['lslim.container'] = $this->container;

            $this->laravel->singleton('files', function ($app) {
                return new Filesystem();
            });
            $composer = new Composer($this->laravel['files'], $this->container->get('app_dir'));

            if ($container->has('db')) {
                Schema::setFacadeApplication($this->laravel);
                DB::setFacadeApplication($this->laravel);

                $resolver = $this->laravel['db'];
                $repository = new Repository($resolver, 'migration');
                $migrator = new Migrator($repository, $resolver, $this->laravel['files']);

                $this->laravel->singleton('migration.creator', function ($app) {
                    return new MigrationCreator($app['files']);
                });

                $this->add(new InstallCommand($repository));
                $this->add(new MigrateCommand($migrator));
                $this->add(new MigrateMakeCommand($this->laravel['migration.creator'], $composer));
                $this->add(new RefreshCommand());
                $this->add(new ResetCommand($migrator));
                $this->add(new RollbackCommand($migrator));
                $this->add(new StatusCommand($migrator));
            }

            if ($container->has('queue')) {
                if (!$this->laravel->bound('cache')) {
                    $this->laravel->singleton('cache', function ($app) {
                        $container = $app['lslim.container'];
                        $path = ContainerFactory::makeConfigPath($container, 'illuminate_cache.php');
                        return CacheFactory::create($container, $path);
                    });
                }

                $trace = debug_backtrace();
                $trace = end($trace);
                $file = $trace['file'];
                $this->laravel->singleton('queue.listener', function ($app) use ($file) {
                    $dir = dirname($file);

                    if (!defined('ARTISAN_BINARY')) {
                        $name = basename($file);
                        define('ARTISAN_BINARY', $name);
                    }
                    return new Listener($dir);
                });

                $this->laravel->singleton(DispatcherContract::class, function ($app) {
                    return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
                        return $app['queue'];
                    });
                });
                $this->laravel->alias(DispatcherContract::class, 'events');

                $this->laravel->singleton('queue.worker', function ($app) {
                    return new Worker(
                        $app['queue'],
                        $app['events'],
                        $this
                    );
                });

                $this->laravel->singleton('queue.failer', function ($app) {
                    $failed = $app['config']['queue.failed'];
                    return isset($failed['table'])
                        ? new DatabaseFailedJobProvider($app['db'], $failed['database'] ?? 'default', $failed['table'])
                        : new NullFailedJobProvider();
                });
                Queue::setFacadeApplication($this->laravel);

                if ($container->has('db')) {
                    $this->add(new FailedTableCommand($this->laravel['files'], $composer));
                    $this->add(new FlushFailedCommand());
                    $this->add(new ForgetFailedCommand());

                    $this->add(new TableCommand($this->laravel['files'], $composer));
                }

                $this->add(new ListenCommand($this->laravel['queue.listener']));
                $this->add(new ListFailedCommand());
                $this->add(new RetryCommand());
                $this->add(new RestartCommand());
                $this->add(new WorkCommand($this->laravel['queue.worker']));

                $this->add(new SupervisorCommand($appName, $file, $container));
            }
        }

        if ($container->has('mailer')) {
            $this->add(new MailTestCommand($this->container, $appName));
        }

        if ($container->has('db')) {
            $this->add(new SessionDatabaseInitCommand());
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
        $this->renderException($e, $output);
    }
}
