<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;
use Illuminate\Support\Arr;
use Traversable;
use InvalidArgumentException;

abstract class UploadedFileManagerBase implements UploadedFileManagerInterface
{
    /**
     * @var string
     */
    private $resourceId;

    /**
     * @var string
     */
    private $sessionKey;

    public function __construct($resourceId, $sessionKey = '__upload')
    {
        $this->resourceId = (string)$resourceId;
        $this->sessionKey = $sessionKey;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        if ($this->willBeDeleted($name)) {
            return false;
        }
        return $this->isFileExists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function willBeDeleted($name)
    {
        $session = $this->getSession($name);
        return $session['delete'] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename($name, $default = null)
    {
        $session = $this->getSession($name);
        return $session['name'] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getStream($name): ?StreamInterface
    {
        if ($this->willBeDeleted($name)) {
            return null;
        }
        return $this->getFileStream($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl($name)
    {
        if ($this->willBeDeleted($name)) {
            return null;
        }
        return $this->getFileUrl($name);
    }

    /**
     * {@inheritdoc}
     */
    public function makeResponseWithFile(ResponseInterface $res, $name): ResponseInterface
    {
        if ($this->willBeDeleted($name)) {
            return $res->withStatus(404);
        }
        return $this->buildFileResponse($res, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function save(ServerRequestInterface $req, $name, $file)
    {
        if (is_array($file) || $file instanceof Traversable) {
            foreach ($file as $k => $f) {
                $this->save($req, $name . '.' . $k, $f);
            }
            return;
        }

        $session = $this->getSession($name);
        if ($file instanceof UploadedFileInterface && $file->getError() === UPLOAD_ERR_OK) {
            $session['name'] = $file->getClientFilename();

            $this->saveFile($name, $file);
            $session['delete'] = false;
        } elseif ($this->shouldDelete($req, $name)) {
            $session['delete'] = true;
        }

        $this->setSession($name, $session);
    }

    /**
     * {@inheritdoc}
     */
    public function moveTo($name, $dst)
    {
        if ($this->willBeDeleted($name)) {
            throw new InvalidArgumentException($name . ' will be deleted.');
        }
        $this->moveFile($name, $dst);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        unset($_SESSION[$this->sessionKey][$this->resourceId]);
        $this->deleteFiles();
    }

    /**
     * @param string $name
     * @return array
     */
    protected function getSession($name)
    {
        $path = $this->sessionKey
            . '.' . $this->resourceId
            . '.' . $name;

        return Arr::get($_SESSION, $path, []);
    }

    /**
     * @param string $name
     * @param array $value
     */
    protected function setSession($name, $value)
    {
        $path = $this->sessionKey
            . '.' . $this->resourceId
            . '.' . $name;

        return Arr::set($_SESSION, $path, $value);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $req
     * @param string $name
     * @return bool
     */
    protected function shouldDelete(ServerRequestInterface $req, $name)
    {
        $body = (array)$req->getParsedBody();
        $v = Arr::get($body, $name, null);

        return $v === '';
    }

    /**
     * {@inheritdoc}
     */
    abstract protected function buildFileResponse(ResponseInterface $res, $name): ResponseInterface;

    /**
     * @param string $name
     * @return \Psr\Http\Message\StreamInterface|null
     */
    abstract protected function getFileStream($name): ?StreamInterface;

    /**
     * @param string $name
     * @return string|null
     */
    abstract protected function getFileUrl($name);

    /**
     * @param string $name
     * @return bool
     */
    abstract protected function isFileExists($name);

    /**
     * @param string $name
     * @param \Psr\Http\Message\UploadedFileInterface $file
     */
    abstract protected function saveFile($name, UploadedFileInterface $file);

    abstract protected function deleteFiles();

    /**
     * @param string $name
     * @param mixed $dst
     */
    abstract protected function moveFile($name, $dst);
}
