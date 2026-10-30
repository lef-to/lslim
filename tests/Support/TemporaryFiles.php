<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use RuntimeException;

trait TemporaryFiles
{
    private array $temporaryFiles = [];

    protected function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lslim-test-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary test file.');
        }
        $this->temporaryFiles[] = $path;
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Cannot write a temporary test file.');
        }
        return $path;
    }

    #[After]
    protected function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];
    }
}
