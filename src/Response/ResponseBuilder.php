<?php

declare(strict_types=1);

namespace LSlim\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Slim\HttpCache\CacheProvider;

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
     * @var CacheProvider
     */
    protected $cacheProvider;

    public function __construct(ResponseInterface $response, ?StreamFactoryInterface $streamFactory = null)
    {
        $this->response         = $response;
        $this->streamFactory    = $streamFactory;
        $this->cacheProvider    = null;
    }

    public function get(): ResponseInterface
    {
        return $this->response;
    }

    public function getCacheProvider(): CacheProvider
    {
        if ($this->cacheProvider === null) {
            $this->cacheProvider = new CacheProvider();
        }
        return $this->cacheProvider;
    }

    /**
     * @param string $path
     * @param string|bool $mimeType
     * @param bool $cacheable
     */
    public function writeFile($path, $mimeType = true, $cacheable = true): static
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

    public function writeJson($value, $option = 0, $depth = 512): static
    {
        $json = json_encode($value, $option, $depth);
        if ($json === false) {
            throw new RuntimeException(json_last_error_msg(), json_last_error());
        }

        $stream = ($this->streamFactory === null)
            ? Utils::streamFor($json)
            : $this->streamFactory->createStream($json);

        $this->response = $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', (string)strlen($json))
            ->withBody($stream);

        return $this;
    }

    protected function makeStream($value, array $options = []): StreamInterface
    {
        if ($this->streamFactory !== null) {
            if (is_string($value)) {
                return $this->streamFactory->createStream($value);
            }
            if (is_resource($value)) {
                return $this->streamFactory->createStreamFromResource($value);
            }
        }
        return Utils::streamFor($value, $options);
    }

    public function setBody($value, array $options = []): static
    {
        $stream = $this->makeStream($value, $options);
        $this->response = $this->response->withBody($stream);

        return $this;
    }

    public function setRedirect($location, $code = 302): static
    {
        $this->response = $this->response
            ->withHeader('Location', $location)
            ->withStatus($code);

        return $this;
    }

    public function setEtag($etag, $weak = false): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->withETag($this->response, $etag, $weak ? 'weak' : 'strong');
        return $this;
    }

    public function setLastModified($time): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->withLastModified($this->response, $time);
        return $this;
    }

    public function allowCache($public = false, $maxAge = 600): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->allowCache($this->response, $public ? 'public' : 'private', $maxAge);

        return $this;
    }

    public function preventCache(): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->denyCache($this->response);

        return $this;
    }

    /*
     * @param int|string|DateTime $time
     */
    public function setExpires($time): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->withExpires($this->response, $time);

        return $this;
    }

    /*
     * @param int $seconds
     */
    public function setRelativeExpires($seconds): static
    {
        $provider = $this->getCacheProvider();
        $this->response = $provider->withExpires($this->response, time() + $seconds);

        return $this;
    }

    public function setAttachment($name, $localizedName): static
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
            $disposition[] = "filename*=UTF-8''" . rawurlencode($localizedName);
        }

        $this->response = $this->response
            ->withHeader('Content-Disposition', implode('; ', $disposition));

        return $this;
    }
}
