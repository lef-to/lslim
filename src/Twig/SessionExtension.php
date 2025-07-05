<?php
declare(strict_types=1);
namespace LSlim\Twig;

use Illuminate\Support\Arr;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SessionExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('session', [ $this, 'session' ])
        ];
    }

    public function session($key, $default = null)
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            return Arr::get($_SESSION, $key, $default);
        }

        return $default;
    }
}
