<?php
declare(strict_types=1);
namespace LSlim\Util;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Intervention\Image\ImageManager;
use Slim\Exception\NotFoundException;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\LimitStream;
use Slim\HttpCache\CacheProvider;
use finfo;

class Response
{
    public static function withCacheTagFromFile(
        ResponseInterface $res,
        $path
    ): ResponseInterface {
        if (file_exists($path)) {
            $cache = new CacheProvider();
            if (!$res->hasHeader('Etag')) {
                $hash = hash_file('sha256', $path);
                $res = $cache->withEtag($res, $hash);
            }
            if (!$res->hasHeader('Last-Modified')) {
                $res = $cache->withLastModified($res, filemtime($path));
            }
        }

        return $res;
    }

    public static function withImage(
        ServerRequestInterface $req,
        ResponseInterface $res,
        ImageManager $imageManager,
        $path,
        array $option = []
    ): ResponseInterface {
        if (!is_file($path)) {
            throw new NotFoundException($req, $res);
        }

        $width = $option['width'] ?? null;
        $height = $option['height'] ?? null;
        $format = $option['format'] ?? null;

        $image = $imageManager->make($path);
        if ($width !== null || $height !== null) {
            $image = $image
                ->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
        }
        return $image->psrResponse($format);
    }

    public static function withDownload(
        ServerRequestInterface $req,
        ResponseInterface $res,
        $path,
        $name = null,
        $localizedName = null
    ): ResponseInterface {
        $header = [
            'attachment; filename="' . $name . '"'
        ];

        if ($name === null) {
            $name = basename($path);
        }

        if ($localizedName) {
            $ext = pathinfo($localizedName, PATHINFO_EXTENSION);
            if (empty($ext)) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if (!empty($ext)) {
                    $ext = '.' . $ext;
                    $localizedName .= $ext;
                }
            }
            $header[] = "filename*=UTF-8*''" . rawurlencode($localizedName);
        }

        if (!$res->hasHeader('Content-Type')) {
            $res = $res->withHeader('Content-Type', 'application/octet-stream');
        }

        $res = $res
            ->withHeader(
                'Content-Disposition',
                implode('; ', $header)
            );

        return static::withFile($req, $res, $path);
    }

    public static function withFile(ServerRequestInterface $req, ResponseInterface $res, $path): ResponseInterface
    {
        if (!is_file($path)) {
            throw new NotFoundException($req, $res);
        }

        if (!$res->hasHeader('Content-Type')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);

            $res = $res->withHeader('Content-Type', $mime);
        }

        $rangeHeader = $req->getHeader('Range');
        if (!empty($rangeHeader)) {
            list($toss, $range) = explode('=', $rangeHeader[0]);
            list($start, $end) = explode('-', $range);

            $filesize = filesize($path);
            if (empty($end)) {
                $end = $filesize - 1;
                $range = sprintf('%d-%d', $start, $end);
            }
            $length = $end - $start + 1;

            $body = new LazyOpenStream($path, 'rb');
            if ($filesize != $length) {
                $body = new LimitStream($body, $length, (int)$start);
            }

            return $res
                ->withStatus(206, 'Partial Content')
                ->withHeader('Content-Length', (string)$length)
                ->withHeader('Content-Range', 'bytes ' . $range . '/' . $filesize)
                ->withBody($body);
        }

        if ($res->hasHeader('Content-Length')) {
            $res = $res->withHeader('Content-Length', (string)filesize($path));
        }

        $stream = new LazyOpenStream($path, 'rb');
        return $res
            ->withBody($stream);
    }
}
