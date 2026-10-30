<?php

declare(strict_types=1);

namespace LSlim\Tests\Support\Queue;

use LSlim\Illuminate\JobHandler;
use RuntimeException;

final class RecordingJobHandler extends JobHandler
{
    protected function handle($args)
    {
        $state = $this->container->get(QueueState::class);
        $state->handled[] = $args;

        if (($args['action'] ?? '') === 'release' && count($state->handled) === 1) {
            $this->release();
        } elseif (($args['action'] ?? '') === 'fail') {
            throw new RuntimeException('Legacy handler failed.');
        }
    }
}
