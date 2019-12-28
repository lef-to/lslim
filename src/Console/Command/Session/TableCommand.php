<?php
declare(strict_types=1);
namespace LSlim\Console\Command\Session;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

class TableCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'session:table {table_name : session table name}';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the session database table.';

    /**
     * @var \Illuminate\Database\Migrations\MigrationCreator
     */
    protected $migrationCreator;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $fileSystem;

    /**
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    public function __construct(MigrationCreator $migrationCreator, Filesystem $fileSystem, Composer $composer)
    {
        parent::__construct();

        $this->migrationCreator = $migrationCreator;
        $this->fileSystem = $fileSystem;
        $this->composer = $composer;
    }

    public function handle()
    {
        $tableName = trim($this->input->getArgument('table_name'));

        $path = $this->migrationCreator->create(
            'create_' . $tableName . '_table',
            $this->laravel->databasePath() . DIRECTORY_SEPARATOR . 'migrations'
        );

        $stubPath = __DIR__
            . DIRECTORY_SEPARATOR
            . 'stub'
            . DIRECTORY_SEPARATOR
            . 'session_table.stub';

        $stub = str_replace(
            [ '{{table}}', '{{tableClassName}}' ],
            [ $tableName, Str::studly($tableName) ],
            $this->fileSystem->get($stubPath)
        );

        $this->fileSystem->put($path, $stub);

        $this->info('Migration is created.');

        $this->composer->dumpAutoloads();
    }
}
