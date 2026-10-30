<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\After;

trait TemporaryDirectories
{
    private array $temporaryDirectories = [];

    protected function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/lslim-test-' . bin2hex(random_bytes(12));
        mkdir($path, 0700);
        $this->temporaryDirectories[] = $path;
        return $path;
    }

    #[After]
    protected function removeTemporaryDirectories(): void
    {
        foreach ($this->temporaryDirectories as $path) {
            (new Filesystem())->deleteDirectory($path);
        }
        $this->temporaryDirectories = [];
    }
}
