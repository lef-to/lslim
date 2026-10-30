<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Http;

use Closure;
use Dflydev\FigCookies\FigResponseCookies;
use LSlim\Container;
use LSlim\Middleware\Session;
use LSlim\Service\CsrfProvider;
use LSlim\Tests\Support\CallbackHandler;
use LSlim\Tests\Support\Psr7;
use LSlim\Tests\Support\TemporaryDirectories;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionCsrfTest extends TestCase
{
    use TemporaryDirectories;

    private const COOKIE = 'LSLIMTEST';

    protected function setUp(): void
    {
        parent::setUp();
        session_save_path($this->temporaryDirectory());
        ini_set('session.gc_probability', '0');
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    private function request(
        string $implementation,
        Closure $action,
        ?string $cookie = null,
        string $method = 'GET',
        array $body = [],
        bool $csrf = false,
        int $regenerateInterval = 0
    ): ResponseInterface {
        // Each invocation simulates a fresh PHP request in the same browser session.
        session_id('');
        $_SESSION = [];
        $factory = Psr7::factory($implementation);
        $request = $factory->createServerRequest($method, 'https://example.test/app/form')
            ->withCookieParams($cookie === null ? [] : [self::COOKIE => $cookie])->withParsedBody($body);
        $handler = new CallbackHandler($action);
        if ($csrf) {
            $next = $handler;
            $handler = new CallbackHandler(static function ($request) use ($factory, $next) {
                $container = Container::setup('testing');
                $container->register(new CsrfProvider($factory, ['storage_limit' => 2]));
                return $container->get('csrf')->process($request, $next);
            });
        }
        return (new Session(self::COOKIE))->setPath('/app')->setSamesite('strict')
            ->setRegenerateInterval($regenerateInterval)->process($request, $handler);
    }

    private function cookie(ResponseInterface $response): string
    {
        return FigResponseCookies::get($response, self::COOKIE)->getValue();
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testCookieContinuesSessionAcrossRequests(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $first = $this->request($implementation, static function () use ($factory) {
            $_SESSION['name'] = '東京';
            return $factory->createResponse();
        });
        $id = $this->cookie($first);
        self::assertNotSame('', $id);
        self::assertStringContainsString('Path=/app', $first->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $first->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('SameSite=Strict', $first->getHeaderLine('Set-Cookie'));
        $second = $this->request($implementation, function () use ($factory) {
            self::assertSame('東京', $_SESSION['name']);
            return $factory->createResponse();
        }, $id);
        self::assertSame($id, $this->cookie($second));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testUnknownSessionIdIsRejected(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $unknown = str_repeat('a', 26);
        $response = $this->request($implementation, static fn () => $factory->createResponse(), $unknown);
        self::assertNotSame($unknown, $this->cookie($response));
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testRegenerationPreservesDataAndRedirectsTheOldId(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $first = $this->request($implementation, static function () use ($factory) {
            $_SESSION['name'] = '東京';
            $_SESSION['__lslim_c'] = time() - 120;
            return $factory->createResponse();
        });
        $oldId = $this->cookie($first);
        $second = $this->request($implementation, static fn () => $factory->createResponse(), $oldId, regenerateInterval: 60);
        $newId = $this->cookie($second);
        self::assertNotSame($oldId, $newId);
        $third = $this->request($implementation, function () use ($factory) {
            self::assertSame('東京', $_SESSION['name']);
            return $factory->createResponse();
        }, $oldId, regenerateInterval: 60);
        self::assertSame($newId, $this->cookie($third));
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testExpiredOldSessionDoesNotRestoreItsData(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $first = $this->request($implementation, static function () use ($factory) {
            $_SESSION = ['name' => 'expired', '__lslim_d' => time() - 301];
            return $factory->createResponse();
        });
        $this->request($implementation, function () use ($factory) {
            self::assertArrayNotHasKey('name', $_SESSION);
            return $factory->createResponse();
        }, $this->cookie($first), regenerateInterval: 60);
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testExceptionClosesAndPersistsTheSession(string $implementation): void
    {
        $id = null;
        try {
            $this->request($implementation, static function () use (&$id) {
                $id = session_id();
                $_SESSION['keep'] = 'saved';
                throw new RuntimeException('handler failed');
            });
            self::fail('Expected an exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('handler failed', $exception->getMessage());
        }
        self::assertSame(PHP_SESSION_NONE, session_status());
        $factory = Psr7::factory($implementation);
        $this->request($implementation, function () use ($factory) {
            self::assertSame('saved', $_SESSION['keep']);
            return $factory->createResponse();
        }, $id);
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testValidCsrfTokenWorksOnceAndCannotBeReplayed(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $token = [];
        $first = $this->request($implementation, static function ($request) use ($factory, &$token) {
            $guard = $request->getAttribute('csrf');
            $token = [$guard->getTokenNameKey() => $guard->getTokenName(), $guard->getTokenValueKey() => $guard->getTokenValue()];
            return $factory->createResponse();
        }, csrf: true);
        $calls = 0;
        $action = static function () use ($factory, &$calls) {
            ++$calls;
            return $factory->createResponse(201);
        };
        $response = $this->request($implementation, $action, $this->cookie($first), 'POST', $token, true);
        self::assertSame(201, $response->getStatusCode());
        $replayed = $this->request($implementation, $action, $this->cookie($response), 'POST', $token, true);
        self::assertSame(302, $replayed->getStatusCode());
        self::assertSame('https://example.test/app/form', $replayed->getHeaderLine('Location'));
        self::assertSame(1, $calls);
    }

    #[DataProviderExternal(Psr7::class, 'implementations')]
    public function testMissingAndTamperedCsrfTokensNeverReachTheHandler(string $implementation): void
    {
        $factory = Psr7::factory($implementation);
        $first = $this->request($implementation, static fn () => $factory->createResponse(), csrf: true);
        foreach ([[], ['csrf_name' => 'forged', 'csrf_value' => 'forged']] as $body) {
            $response = $this->request($implementation, static function () {
                self::fail('Invalid CSRF token reached the handler.');
            }, $this->cookie($first), 'POST', $body, true);
            self::assertSame(302, $response->getStatusCode());
        }
    }
}
