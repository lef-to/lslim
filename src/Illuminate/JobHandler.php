<?php
declare(strict_types=1);
namespace LSlim\Illuminate;

use Illuminate\Queue\Jobs\Job as QueueJob;
use LSlim\Traits\HasContainer;
use Exception;

abstract class JobHandler
{
    use HasContainer;

    /** @var \Illuminate\Queue\Jobs\Job */
    private $job;

    public function fire(QueueJob $job, $args)
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
            $this->getLogger()->error(
                'failed to handle job.',
                [
                    'name' => $this->job->getName(),
                    'exception' => $ex
                ]
            );
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->fail($ex);
            }
        }
    }

    protected function delete()
    {
        $this->job->delete();
    }

    protected function release()
    {
        $this->job->release();
    }

    abstract protected function handle($args);
}
