<?php
declare(strict_types=1);
namespace LSlim\Form;

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

    /**
     * {@inheritdoc}
     */
    protected function getFileStream($name): ?StreamInterface
    {
        $path = $this->getPath($name);
        if ($path && is_file($path)) {
            return new LazyOpenStream($path, "rb");
        }
        return null;
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

    /**
     * {@inheritdoc}
     */
    protected function getFileContentType($name)
    {
        $path = $this->getPath($name);
        if ($path && is_file($path)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return $finfo->file($path);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileSize($name)
    {
        $path = $this->getPath($name);
        if ($path && is_file($path)) {
            return filesize($path);
        }
        return null;
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
