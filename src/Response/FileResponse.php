<?php
declare(strict_types=1);
namespace LSlim\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\LazyOpenStream;
use Slim\HttpCache\CacheProvider;
use finfo;
use RuntimeException;

class FileResponse
{
    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * @var string $path
     */
    protected $path;

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $path
     */
    public static function create(ResponseInterface $response, $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException($path . " is not exist.");
        }
        return new static($response, $path);
    }

    private function __construct(ResponseInterface $response, $path)
    {
        $this->path = $path;
        $stream = new LazyOpenStream($path, 'rb');
        $this->response = $response->withBody($stream);
    }

    public function withCacheTag(): self
    {
        $cache = new CacheProvider();

        if (!$this->response->hasHeader('Etag')) {
            $hash = hash_file('sha256', $this->path);
            $this->response = $cache->withEtag($this->response, $hash);
        }

        if (!$this->response->hasHeader('Last-Modified')) {
            $mtime = filemtime($this->path);
            if ($mtime !== false) {
                $this->response = $cache->withLastModified($this->response, $mtime);
            }
        }

        return $this;
    }

    public function withAttachment($name, $localizedName): self
    {
        $disposition = [
            'attachment'
        ];

        if ($name) {
            $disposition[] = 'filename="' . $name . '"';
        }

        if ($localizedName) {
            $ext = pathinfo($localizedName, PATHINFO_EXTENSION);
            if (empty($ext) && $name) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if (!empty($ext)) {
                    $ext = '.' . $ext;
                    $localizedName .= $ext;
                }
            }
            $disposition[] = "filename*=UTF-8*''" . rawurlencode($localizedName);
        }

        $this->response->withHeader('Content-Disposition', implode('; ', $disposition));
        return $this;
    }

    public function withMimeType(): self
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($this->path);

        if ($mime !== false) {
            $this->response = $this->response->withHeader('Content-Type', $mime);
        }

        return $this;
    }

    public function getResponse(): ResponseInterface
    {
        if (!$this->response->hasHeader('Content-Length')) {
            $size = filesize($this->path);
            if ($size !== false) {
                $this->response = $this->response->withHeader('Content-Length', (string)$size);
            }
        }

        if (!$this->response->hasHeader('Content-Type')) {
            $this->response = $this->response->withHeader('Content-Type', 'application/octet-stream');
        }
        return $this->response;
    }
}
