<?php

declare(strict_types=1);

namespace LSlim\Tests\Support\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use LSlim\Illuminate\Queueable;
use RuntimeException;
use Throwable;

final class RecordingQueueableJob implements ShouldQueue
{
    use Queueable;

    public ?string $connection = null;
    public ?string $queue = null;

    public function __construct(public string $message, private int $failForAttempts = 0)
    {
        $this->tries = 2;
    }

    public function handle(): void
    {
        $container = $this->getContainer();
        $container->get(QueueState::class)->handled[] = [
            'message' => $this->message,
            'attempt' => $this->attempts(),
            'environment' => $container->get('env'),
        ];
        if ($this->attempts() <= $this->failForAttempts && !$container->get(QueueState::class)->allowSuccess) {
            throw new RuntimeException('Queueable job failed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->getContainer()->get(QueueState::class)->failed[] = $exception->getMessage();
    }
}
