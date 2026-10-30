<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Form;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use LSlim\Form\UploadedFileManager;
use LSlim\Tests\Support\SessionState;
use LSlim\Tests\Support\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

final class UploadedFileManagerTest extends TestCase
{
    use SessionState;
    use TemporaryDirectories;

    public function testSaveResponseAndMoveToFinalStorage(): void
    {
        $root = $this->temporaryDirectory();
        $files = new UploadedFileManager($root, 'resource');
        $files->save(new ServerRequest('POST', '/'), 'document', new UploadedFile(Utils::streamFor('hello'), 5, UPLOAD_ERR_OK, '資料.txt'));
        self::assertTrue($files->has('document'));
        self::assertSame('資料.txt', $files->getClientFilename('document'));
        $response = $files->makeResponseWithFile(new Response(), 'document');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('5', $response->getHeaderLine('Content-Length'));
        self::assertSame('hello', (string) $response->getBody());
        $response->getBody()->close();
        $files->moveTo('document', $root . '/final/document.txt');
        self::assertSame('hello', file_get_contents($root . '/final/document.txt'));
        self::assertFalse($files->has('document'));
        $files->clear();
        self::assertFileExists($root . '/final/document.txt');
    }

    public function testDeleteMarkerHidesTheFileAndPreventsMovingIt(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'resource');
        $request = new ServerRequest('POST', '/');
        $files->save($request, 'document', new UploadedFile(Utils::streamFor('hello'), 5, UPLOAD_ERR_OK, 'test.txt'));
        $files->save($request->withParsedBody(['document' => '']), 'document', null);
        self::assertTrue($files->willBeDeleted('document'));
        self::assertFalse($files->has('document'));
        self::assertNull($files->getStream('document'));
        self::assertNull($files->getUrl('document'));
        self::assertSame(404, $files->makeResponseWithFile(new Response(), 'document')->getStatusCode());
        $this->expectException(InvalidArgumentException::class);
        $files->moveTo('document', '/unused');
    }

    public function testReplacingAFileResetsItsDeleteMarker(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'resource');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['document' => '']);
        $files->save($request, 'document', null);
        $files->save($request, 'document', new UploadedFile(Utils::streamFor('new'), 3, UPLOAD_ERR_OK, 'new.txt'));
        self::assertFalse($files->willBeDeleted('document'));
        self::assertTrue($files->has('document'));
        self::assertSame('new.txt', $files->getClientFilename('document'));
    }

    public function testClearingOneResourcePreservesAnotherResource(): void
    {
        $root = $this->temporaryDirectory();
        $first = new UploadedFileManager($root, 'first');
        $second = new UploadedFileManager($root, 'second');
        foreach ([$first, $second] as $files) {
            $files->save(new ServerRequest('POST', '/'), 'document', new UploadedFile(Utils::streamFor('keep'), 4, UPLOAD_ERR_OK, 'test.txt'));
        }
        $first->clear();
        self::assertFalse($first->has('document'));
        self::assertTrue($second->has('document'));
        self::assertSame('test.txt', $second->getClientFilename('document'));
    }

    public function testMissingFileReturns404AndCannotBeMoved(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'resource');
        self::assertNull($files->getStream('missing'));
        self::assertNull($files->getUrl('missing'));
        self::assertSame(404, $files->makeResponseWithFile(new Response(), 'missing')->getStatusCode());
        $this->expectException(InvalidArgumentException::class);
        $files->moveTo('missing', '/unused');
    }
}
