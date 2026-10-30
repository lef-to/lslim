<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Validation;

use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use LSlim\Validation\Rules\UploadedFile as UploadedFileRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    #[DataProvider('uploadErrors')]
    public function testValidatesTheServerUploadError(int $error, bool $expected): void
    {
        $rule = new UploadedFileRule();
        $file = new UploadedFile(Utils::streamFor('contents'), 8, $error, 'example.txt', 'text/plain');
        self::assertSame($expected, $rule->isValid($file));
        self::assertSame('example.txt', $rule->fileName);
        self::assertSame($error, $rule->errorCode);
    }

    public static function uploadErrors(): iterable
    {
        yield 'success' => [UPLOAD_ERR_OK, true];
        yield 'server size limit' => [UPLOAD_ERR_INI_SIZE, false];
        yield 'form size limit' => [UPLOAD_ERR_FORM_SIZE, false];
        yield 'partial upload' => [UPLOAD_ERR_PARTIAL, false];
        yield 'no file' => [UPLOAD_ERR_NO_FILE, false];
        yield 'no temporary directory' => [UPLOAD_ERR_NO_TMP_DIR, false];
        yield 'disk write failure' => [UPLOAD_ERR_CANT_WRITE, false];
        yield 'extension rejected upload' => [UPLOAD_ERR_EXTENSION, false];
    }

    public function testRejectsValuesThatAreNotUploadedFiles(): void
    {
        $rule = new UploadedFileRule();
        self::assertFalse($rule->isValid(null));
        self::assertFalse($rule->isValid('/tmp/example.txt'));
    }
}
