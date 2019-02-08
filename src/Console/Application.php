<?php
declare(strict_types=1);
namespace LSlim\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Illuminate\Database\Capsule\Manager as Database;
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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command as IlluminateCommand;
use LSlim\Console\Container;
use LSlim\Console\Command\Mail\TestCommand as MailTestCommand;

class Application extends BaseApplication
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var \LSlim\Console\Container
     */
    private $laravel;

    public function __construct(ContainerInterface $container, $appName, $version)
    {
        parent::__construct($appName, $version);
        $this->container = $container;
        $this->laravel = new Container($this->container, $version);

        $this->laravel->singleton('db', function ($app) {
            $db = $this->container->get('db');
            return $db->getDatabaseManager();
        });

        Schema::setFacadeApplication($this->laravel);
        DB::setFacadeApplication($this->laravel);

        $resolver = $this->laravel['db'];
        $repository = new Repository($resolver, 'migration');
        $filesystem = new Filesystem();
        $migrator = new Migrator($repository, $resolver, $filesystem);
        $composer = new Composer($filesystem, $this->container->get('app_dir'));
        $migrationCreator = new MigrationCreator($filesystem);

        $this->add(new InstallCommand($repository));
        $this->add(new MigrateCommand($migrator));
        $this->add(new MigrateMakeCommand($migrationCreator, $composer));
        $this->add(new RefreshCommand());
        $this->add(new ResetCommand($migrator));
        $this->add(new RollbackCommand($migrator));
        $this->add(new StatusCommand($migrator));
        $this->add(new MailTestCommand($this->container, $appName));
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
}
