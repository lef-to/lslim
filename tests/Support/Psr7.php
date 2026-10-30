<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use GuzzleHttp\Psr7\HttpFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

final class Psr7
{
    public static function implementations(): iterable
    {
        yield 'Guzzle' => ['guzzle'];
        yield 'Nyholm' => ['nyholm'];
    }

    public static function factory(string $implementation): HttpFactory|Psr17Factory
    {
        return $implementation === 'guzzle' ? new HttpFactory() : new Psr17Factory();
    }
}
