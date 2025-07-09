<?php

declare(strict_types=1);

namespace LSlim\Illuminate;

use Illuminate\Queue\Jobs\Job;
use Exception;
use Psr\Container\ContainerInterface;
use RuntimeException;

abstract class JobHandler
{
    /** @var \Illuminate\Queue\Jobs\Job */
    private $job = null;

    /** @var \Psr\Container\ContainerInterface|null */
    protected $container = null;

    public function fire(Job $job, $args)
    {
        $this->job = $job;

        /** @var \Illumminate\Contracts\Container\Container */
        $laravel = $job->getContainer();
        $this->container = $laravel['lslim.container'];

        try {
            $this->handle($args);

            if (!$job->isDeletedOrReleased()) {
                $job->delete();
            }
        } catch (Exception $ex) {
            $this->container->get('logger')->error(
                'Failed to handle job.',
                [
                    'name' => $this->job->getName(),
                    'exception' => $ex
                ]
            );
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->fail($ex);
            }
        } finally {
            $this->job = null;
        }
    }

    protected function delete()
    {
        if ($this->job !== null) {
            $this->job->delete();
        }
    }

    protected function release()
    {
        if ($this->job !== null) {
            $this->job->release();
        }
    }

    abstract protected function handle($args);
}
