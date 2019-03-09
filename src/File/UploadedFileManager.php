<?php
declare(strict_types=1);
namespace LSlim\File;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class UploadedFileManager
{
    /**
     * @var string
     */
    private $uploadDir;

    /**
     * @var string
     */
    private $resourceId;

    /**
     * @var string
     */
    private $sessionKey;

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
        $this->sessionKey = $sessionKey;
        $this->resourceId = (string)$resourceId;
        $this->uploadDir = rtrim($rootDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . trim($this->resourceId, DIRECTORY_SEPARATOR);
        $this->fs = new Filesystem();
    }

    public function getPath($name)
    {
        return $this->uploadDir . DIRECTORY_SEPARATOR . trim((string)$name, DIRECTORY_SEPARATOR);
    }

    public function clear()
    {
        unset($_SESSION[$this->sessionKey][$this->resourceId]);
        if ($this->fs->isDirectory($this->uploadDir)) {
            $this->fs->deleteDirectory($this->uploadDir);
        }
    }

    protected function getStorage($name, $default = [])
    {
        $path = $this->sessionKey
            . '.' . $this->resourceId
            . '.' . $name;

        return Arr::get($_SESSION, $path, $default);
    }

    protected function setStorage($name, $value)
    {
        $path = $this->sessionKey
            . '.' . $this->resourceId
            . '.' . $name;

        return Arr::set($_SESSION, $path, $value);
    }


    /**
     * @param string $name
     * @return bool
     */
    public function willBeDeleted($name)
    {
        $session = $this->getStorage($name);
        return $session['delete'] ?? false;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getClientFilename($name, $default = null)
    {
        $session = $this->getStorage($name);
        return $session['name'] ?? $default;
    }

    public function save(ServerRequestInterface $req, $name, $file)
    {
        if (is_array($file)) {
            foreach ($file as $k => $f) {
                $this->save($req, $name . '.' . $k, $f);
            }
            return;
        }

        $session = $this->getStorage($name);
        if ($file instanceof UploadedFileInterface && $file->getError() === UPLOAD_ERR_OK) {
            $session['name'] = $file->getClientFilename();

            $dstPath = $this->getPath($name);
            $this->makeDirectory(dirname($dstPath));

            $file->moveTo($dstPath);
            $session['delete'] = false;
        } elseif ($this->shouldDelete($req, $name)) {
            $session['delete'] = true;
        }

        $this->setStorage($name, $session);
    }

    protected function shouldDelete(ServerRequestInterface $req, $name)
    {
        $body = (array)$req->getParsedBody();
        $v = Arr::get($body, $name, null);

        return $v === '';
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        if ($this->willBeDeleted($name)) {
            return false;
        }

        $path = $this->getPath($name);
        return $path && is_file($path);
    }

    /**
     * @param string $name
     * @param string $dstPath
     */
    public function release($name, $dstPath)
    {
        if ($this->willBeDeleted($name)) {
            if ($this->fs->isFile($dstPath)) {
                if (!$this->fs->delete($dstPath)) {
                    throw new RuntimeException('Failed to delete ' . $dstPath);
                }
                return;
            }
        }

        $srcPath = $this->getPath($name);
        if ($srcPath && $this->fs->isFile($srcPath)) {
            $dir = dirname($dstPath);
            $this->makeDirectory($dir);

            if (!$this->fs->copy($srcPath, $dstPath)) {
                throw new RuntimeException('Failed to copy file to ' . $dstPath);
            }
        }
    }

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
