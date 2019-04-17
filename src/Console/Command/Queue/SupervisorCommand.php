<?php
declare(strict_types=1);
namespace LSlim\Console\Command\Queue;

use Illuminate\Console\Command;
use Symfony\Component\Process\PhpExecutableFinder;
use Psr\Container\ContainerInterface;

class SupervisorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:supervisor'
        . ' {--user=apache : account which runs the program}'
        . ' {--num-procs=1 : number of processes}';

    /**
     * @var string
     */
    protected $description = 'Make supervisor configuration.';

    /**
     * @var string
     */
    private $appName;

    /**
     * @var string
     */
    private $file;

    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    public function __construct($appName, $file, $container)
    {
        parent::__construct();
        $this->appName = $appName;
        $this->file = $file;
        $this->container = $container;
    }

    public function handle()
    {
        $phpPath = (new PhpExecutableFinder())->find(false);
        $user = $this->option('user');
        $numProcs = $this->option('num-procs');

        $appName = $this->appName . '-worker';
        $logPath = rtrim($this->container->get('log_dir'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $appName . '.log';

        $ret = <<< EOF
[program:{$appName}]
process_name=%(program_name)s_%(process_num)02d
command={$phpPath} {$this->file} queue:work
autostart=true
autorestart=true
user={$user}
numprocs={$numProcs}
redirect_stderr=true
stdout_logfile={$logPath}
EOF;
        echo $ret . "\n";
    }
}
