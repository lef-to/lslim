<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait SessionState
{
    private bool $hadSession;
    private array $savedSession;

    #[Before]
    protected function prepareSessionState(): void
    {
        $this->hadSession = isset($_SESSION);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    #[After]
    protected function restoreSessionState(): void
    {
        if ($this->hadSession) {
            $_SESSION = $this->savedSession;
        } else {
            unset($_SESSION);
        }
    }
}
