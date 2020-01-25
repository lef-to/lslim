<?php
declare(strict_types=1);
namespace LSlim\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TextExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('shrink', [ $this, 'shrink' ])
        ];
    }

    public function shrink($value, $length, $width, $sep = '...')
    {
        $l = mb_strlen($value);
        if ($l < $length) {
            return $value;
        }

        return mb_substr($value, 0, $width) . $sep . mb_substr($value, $l - $width, $l);
    }
}
