<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

interface UploadedFileManagerInterface
{
    /**
     * @param string $name
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function makeResponseWithFile(ResponseInterface $res, $name): ResponseInterface;

    /**
     * @param string $name
     * @return \Psr\Http\Message\StreamInterface|null
     */
    public function getStream($name): ?StreamInterface;

    /**
     * @param string $name
     * @return string|null
     */
    public function getUrl($name);

    public function clear();

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getClientFilename($name, $default = null);

    /**
     * @param string $name
     * @return bool
     */
    public function willBeDeleted($name);

    /**
     * @param string $name
     * @return bool
     */
    public function has($name);

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $req
     * @param string $name
     * @param \Psr\Http\Message\UploadedFileInterface|array|\ArrayAccess|null $file
     */
    public function save(ServerRequestInterface $req, $name, $file);

    /**
     * @param string $name
     * @param mixed $dst
     */
    public function moveTo($name, $dst);
}
