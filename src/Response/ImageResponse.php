<?php
declare(strict_types=1);
namespace LSlim\Response;

use Psr\Http\Message\ResponseInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Image;

class ImageResponse
{
    /**
     * @var \Intervention\Image\Image
     */
    protected $image;

    /**
     * @var string
     */
    protected $path;

    public static function create(ImageManager $imageManager, $path)
    {
        return new static($imageManager->make($path), $path);
    }

    public function __construct(Image $image, $path)
    {
        $this->image = $image;
        $this->path = $path;
    }

    public function resize($width = null, $height = null): self
    {
        if ($width !== null || $height !== null) {
            $this->image = $this->image
                ->resize($width, $height, function ($constraint) {
                    $constraint->aspectRatio();
                });
        }

        return $this;
    }

    public function makeResponse($format = null, $quality = 90): ResponseInterface
    {
        return $this->image->psrResponse($format, $quality);
    }

    public function makeFileResponse($format = null, $quality = 90): FileResponse
    {
        return FileResponse::create($this->makeResponse($format, $quality), $this->path);
    }
}
