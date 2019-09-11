<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Illuminate\Filesystem\Filesystem;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\LazyOpenStream;
use RuntimeException;
use finfo;

class UploadedFileManager extends UploadedFileManagerBase
{
    /**
     * @var string
     */
    private $uploadDir;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * @param string $rootDir
     * @param string|int $resourceId
     * @param string $sessionKey
     */
    public function __construct($rootDir, $resourceId, $sessionKey = '__upload')
    {
        parent::__construct($resourceId, $sessionKey);
        $this->uploadDir = rtrim($rootDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . trim((string)$resourceId, DIRECTORY_SEPARATOR);

        $this->fs = new Filesystem();
    }

    protected function getPath($name)
    {
        return $this->uploadDir . DIRECTORY_SEPARATOR . trim((string)$name, DIRECTORY_SEPARATOR);
    }

    protected function makeStream($path): ?StreamInterface
    {
        if ($path && is_file($path)) {
            return new LazyOpenStream($path, "rb");
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFileResponse(ResponseInterface $res, $name): ResponseInterface
    {
        $path = $this->getPath($name);
        $stream = $this->makeStream($path);
        if ($stream === null) {
            return $res->withStatus(404);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $res
            ->withBody($stream)
            ->withHeader('Content-Type', $finfo->file($path))
            ->withHeader('Content-Length', (string)filesize($path));
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileStream($name): ?StreamInterface
    {
        $path = $this->getPath($name);
        return $this->makeStream($path);
    }

    /**
     * {@inheritdoc}
     */
    protected function saveFile($name, UploadedFileInterface $file)
    {
        $dstPath = $this->getPath($name);
        $this->makeDirectory(dirname($dstPath));
        $file->moveTo($dstPath);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteFiles()
    {
        if ($this->fs->isDirectory($this->uploadDir)) {
            $this->fs->deleteDirectory($this->uploadDir);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function isFileExists($name)
    {
        $path = $this->getPath($name);
        return $path && is_file($path);
    }

    // /**
    //  * @param string $name
    //  * @param string $dstPath
    //  */
    // public function release($name, $dstPath)
    // {
    //     if ($this->willBeDeleted($name)) {
    //         if ($this->fs->isFile($dstPath)) {
    //             if (!$this->fs->delete($dstPath)) {
    //                 throw new RuntimeException('Failed to delete ' . $dstPath);
    //             }
    //             return;
    //         }
    //     }

    //     $srcPath = $this->getPath($name);
    //     if ($srcPath && $this->fs->isFile($srcPath)) {
    //         $dir = dirname($dstPath);
    //         $this->makeDirectory($dir);

    //         if (!$this->fs->copy($srcPath, $dstPath)) {
    //             throw new RuntimeException('Failed to copy file to ' . $dstPath);
    //         }
    //     }
    // }

    protected function makeDirectory($path)
    {
        if (!$this->fs->isDirectory($path)) {
            $this->fs->makeDirectory($path, 0775, true);

            if (!$this->fs->isDirectory($path)) {
                throw new RuntimeException('Failed to make ' . $path . ' directory.');
            }
        }
    }
}
