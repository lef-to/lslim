<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit;

use GuzzleHttp\Psr7\ServerRequest;
use LSlim\Container;
use LSlim\Response\ResponseBuilder;
use LSlim\Service\NyholmPsr17Provider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Slim\Interfaces\RouteParserInterface;

final class ContainerTest extends TestCase
{
    public function testCreatesASlimApplicationWithPsrFactoriesAndBasePath(): void
    {
        $container = Container::setup('testing');
        $container->register(new NyholmPsr17Provider());
        $app = $container->createApplication('/app');
        $app->get('/hello/{name}', function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $arguments
        ): ResponseInterface {
            return (new ResponseBuilder($response))->writeJson(['name' => $arguments['name']])->get();
        })->setName('hello');

        $response = $app->handle(new ServerRequest('GET', 'http://example.test/app/hello/world'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['name' => 'world'], json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame('/app/hello/world', $container->get(RouteParserInterface::class)->urlFor('hello', ['name' => 'world']));
        self::assertSame($container->get(ResponseFactoryInterface::class), $container->get(StreamFactoryInterface::class));
    }
}
