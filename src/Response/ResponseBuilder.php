<?php
declare(strict_types=1);
namespace LSlim\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Micheh\Cache\CacheUtil;
use GuzzleHttp\Psr7\LazyOpenStream;
use function GuzzleHttp\Psr7\stream_for;
use RuntimeException;

class ResponseBuilder
{
    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * @var \Psr\Http\Message\StreamFactoryInterface|null
     */
    protected $streamFactory;

    /**
     * @var \Micheh\Cache\CacheUtil
     */
    protected $util = null;

    public function __construct(ResponseInterface $response, ?StreamFactoryInterface $streamFactory = null)
    {
        $this->response = $response;
        $this->streamFactory = $streamFactory;
    }

    public function get(): ResponseInterface
    {
        return $this->response;
    }

    protected function getUtil(): CacheUtil
    {
        if ($this->util === null) {
            $this->util = new CacheUtil();
        }
        return $this->util;
    }

    /**
     * @param string $path
     * @param string|bool $mimeType
     * @param bool $cacheable
     */
    public function writeFile($path, $mimeType = true, $cacheable = true): self
    {
        $stream = ($this->streamFactory === null)
            ? new LazyOpenStream($path, 'rb')
            : $this->streamFactory->createStreamFromFile($path);

        $this->response = $this->response->withBody($stream);

        if ($mimeType === true) {
            $mimeType = mime_content_type($path);
        }
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        $this->response = $this->response->withHeader('Content-Type', $mimeType);

        if ($cacheable) {
            $this->setEtag(hash_file('sha256', $path));
            $this->setLastModified(filemtime($path));
        }

        $this->response = $this->response->withHeader('Content-Length', (string)filesize($path));
        return $this;
    }

    public function writeJson($value, $option = 0, $depth = 512): self
    {
        $json = json_encode($value, $option, $depth);
        if ($json === false) {
            throw new RuntimeException(json_last_error_msg(), json_last_error());
        }

        $stream = ($this->streamFactory === null)
            ? stream_for($json)
            : $this->streamFactory->createStream($json);

        $this->response = $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', (string)strlen($json))
            ->withBody($stream);

        return $this;
    }

    public function setRedirect($location, $code = 302)
    {
        $this->response = $this->response
            ->withHeader('Location', $location)
            ->withStatus($code);
    }

    public function setEtag($etag, $weak = false): self
    {
        $util = $this->getUtil();
        $this->response = $util->withETag($this->response, $etag, $weak);
        return $this;
    }

    public function setLastModified($time)
    {
        $util = $this->getUtil();
        $this->response = $util->withLastModified($this->response, $time);
        return $this;
    }

    public function allowCache($public = false, $maxAge = 600): self
    {
        $util = $this->getUtil();
        $this->response = $util->withCache($this->response, $public, $maxAge);

        return $this;
    }

    public function preventCache(): self
    {
        $util = $this->getUtil();
        $this->response = $util->withCachePrevention($this->response);

        return $this;
    }

    /*
     * @param int|string|DateTime $time
     */
    public function setExpires($time): self
    {
        $util = $this->getUtil();
        $this->response = $util->withExpires($this->response, $time);

        return $this;
    }

    /*
     * @param int $seconds
     */
    public function setRelativeExpires($seconds): self
    {
        $util = $this->getUtil();
        $this->response = $util->withRelativeExpires($this->response, $seconds);

        return $this;
    }

    public function setAttachment($name, $localizedName): self
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

        $this->response = $this->response
            ->withHeader('Content-Disposition', implode('; ', $disposition));

        return $this;
    }
}
