<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Illuminate\Queue\InteractsWithQueue;
use Psr\Container\ContainerInterface;
use Illuminate\Queue\Jobs\Job;
use RuntimeException;

trait Queueable
{
    use InteractsWithQueue;

    /**
     * @var int|null 最大試行回数
     */
    public $tries = 1;

    /**
     * @var int|null ジョブを再試行するまでに待つ秒数
     */
    public $retryAfter = 30;

    protected function getContainer(): ContainerInterface
    {
        $job = $this->job;
        if ($job instanceof Job) {
            $laravel = $job->getContainer();
            return $laravel['lslim.container'];
        }
        throw new RuntimeException('Job does not have container.');
    }
}
