<?php

declare(strict_types=1);

namespace LSlim\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LSlim\Form\FormTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class FormHarness
{
    use FormTrait;

    public function __construct(array $options = [])
    {
        $this->data = $options;
    }

    protected function getLogger(): LoggerInterface
    {
        return new NullLogger();
    }

    public function run(ServerRequestInterface $request, callable $action, string $name = 'test-form'): ResponseInterface
    {
        return $this->processForm($request, new Response(), $action, $name);
    }

    public function values(): array
    {
        return $this->data;
    }
}
