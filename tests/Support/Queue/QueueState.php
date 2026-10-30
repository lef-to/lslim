<?php

declare(strict_types=1);

namespace LSlim\Tests\Support\Queue;

final class QueueState
{
    public array $handled = [];
    public array $failed = [];
    public bool $allowSuccess = false;
}
