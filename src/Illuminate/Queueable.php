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
