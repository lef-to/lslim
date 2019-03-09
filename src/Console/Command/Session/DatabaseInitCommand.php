<?php
declare(strict_types=1);
namespace LSlim\Console\Command\Session;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DatabaseInitCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'session:db:init {table_name : session table name}';

    /**
     * @var string
     */
    protected $description = 'Create session table.';

    public function handle()
    {
        $tableName = trim($this->input->getArgument('table_name'));

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->longText('data');
                $table->integer('ts')->unsigned();
            });
            $this->info('Table ' . $tableName . ' is created.');
        } else {
            $this->error('Table ' . $tableName . ' is already created.');
        }
    }
}
