<?php
declare(strict_types=1);
namespace LSlim\Service;

use Illuminate\Queue\Capsule\Manager as Queue;

trait HasQueue
{
    abstract protected function getQueue(): Queue;

    protected function pushJob($job, $data = '', $queue = null, $connectionName = null)
    {
        $this
            ->getQueue()
            ->getQueueManager()
            ->connection($connectionName)
            ->push($job, $data, $queue);
    }

    protected function pushJobLater($delay, $job, $data = '', $queue = null, $connectionName = null)
    {
        $this
            ->getQueue()
            ->getQueueManager()
            ->connection($connectionName)
            ->later($delay, $job, $data, $queue);
    }
}
