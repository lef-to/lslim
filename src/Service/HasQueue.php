<?php
declare(strict_types=1);
namespace LSlim\Service;

use Illuminate\Queue\QueueManager;

trait HasQueue
{
    abstract protected function getQueue(): QueueManager;

    protected function pushJob($job, $data = '', $queue = null, $connectionName = null)
    {
        $this
            ->getQueue()
            ->connection($connectionName)
            ->push($job, $data, $queue);
    }

    protected function pushJobLater($delay, $job, $data = '', $queue = null, $connectionName = null)
    {
        $this
            ->getQueue()
            ->connection($connectionName)
            ->later($delay, $job, $data, $queue);
    }
}
