<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Form;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use LSlim\Form\Option;
use LSlim\Form\Phase;
use LSlim\Form\UploadedFileManager;
use LSlim\Tests\Support\FormHarness;
use LSlim\Tests\Support\SessionState;
use LSlim\Tests\Support\TemporaryDirectories;
use LSlim\Validation\Validator;
use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use RuntimeException;

final class FormTest extends TestCase
{
    use SessionState;
    use TemporaryDirectories;

    private function form(?UploadedFileManager $files = null): FormHarness
    {
        $validator = (new Validator())->add('name', v::stringType()->notEmpty());
        if ($files !== null) {
            $validator->add('document', v::alwaysValid(), ['type' => 'file']);
        }
        return new FormHarness([Option::VALIDATOR => $validator, Option::UPLOADED => $files]);
    }

    private function post(array $body): ServerRequest
    {
        return (new ServerRequest('POST', 'http://example.test/form'))->withParsedBody($body);
    }

    public function testConfirmThenCompleteUsesServerStoredValues(): void
    {
        $phases = [];
        $action = static function ($response, $phase) use (&$phases) {
            $phases[] = $phase;
            return $response;
        };
        $confirm = $this->form();
        $confirm->run($this->post(['name' => ' 東京 ', '__form_phase' => 'confirm']), $action);
        self::assertSame(['name' => '東京'], $_SESSION['__form']['test-form']['input']);
        $complete = $this->form();
        $complete->run($this->post(['name' => 'tampered', '__form_phase' => 'confirmed']), $action);
        self::assertSame([Phase::CONFIRM, Phase::COMPLETE], $phases);
        self::assertSame(['name' => '東京'], $complete->values()[Option::INPUT]);
    }

    public function testBackRedirectRestoresInputOnTheNextGetOnly(): void
    {
        $_SESSION['__form']['test-form']['input'] = ['name' => '東京'];
        $response = $this->form()->run($this->post(['__form_phase' => 'confirmed', '__form_back' => '1']), static fn ($r, $p) => $r);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('http://example.test/form', $response->getHeaderLine('Location'));
        $form = $this->form();
        $form->run(new ServerRequest('GET', 'http://example.test/form'), static fn ($r, $p) => $r);
        self::assertSame(['name' => '東京'], $form->values()[Option::INPUT]);
        self::assertArrayNotHasKey('test-form', $_SESSION['__form']);
    }

    public function testUnconfirmedPostDoesNotExecuteTheAction(): void
    {
        $called = false;
        $response = $this->form()->run($this->post(['__form_phase' => 'confirmed']), static function ($r, $p) use (&$called) {
            $called = true;
            return $r;
        });
        self::assertFalse($called);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testValidationFailureRetainsInputAndUploadedFile(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'form');
        $form = $this->form($files);
        $request = $this->post(['name' => ' ', '__form_phase' => 'confirm'])->withUploadedFiles([
            'document' => new UploadedFile(Utils::streamFor('keep'), 4, UPLOAD_ERR_OK, '資料.txt'),
        ]);
        $phases = [];
        $form->run($request, static function ($r, $p) use (&$phases) {
            $phases[] = $p;
            return $r;
        });
        self::assertSame([Phase::INPUT], $phases);
        self::assertSame(['name' => null], $form->values()[Option::INPUT]);
        self::assertTrue($files->has('document'));
        self::assertSame('資料.txt', $files->getClientFilename('document'));
        $stream = $files->getStream('document');
        self::assertSame('keep', (string) $stream);
        $stream->close();
    }

    public function testSuccessfulCompletionClearsFilesButAnExceptionKeepsThem(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'form');
        $files->save($this->post([]), 'document', new UploadedFile(Utils::streamFor('keep'), 4, UPLOAD_ERR_OK, 'test.txt'));
        $_SESSION['__form']['test-form']['input'] = ['name' => '東京'];
        $form = $this->form($files);
        $phases = [];
        $form->run($this->post(['__form_phase' => 'confirmed']), static function ($r, $p) use (&$phases) {
            $phases[] = $p;
            if ($p === Phase::COMPLETE) {
                throw new RuntimeException('business operation failed');
            }
            return $r;
        });
        self::assertSame([Phase::COMPLETE, Phase::CONFIRM], $phases);
        self::assertTrue($files->has('document'));
        self::assertSame('business operation failed', $form->values()[Option::EXCEPTION]->getMessage());
        $this->form($files)->run($this->post(['__form_phase' => 'confirmed']), static fn ($r, $p) => $r);
        self::assertFalse($files->has('document'));
        self::assertArrayNotHasKey('form', $_SESSION['__upload']);
    }

    public function testInitialGetClearsAbandonedFiles(): void
    {
        $files = new UploadedFileManager($this->temporaryDirectory(), 'form');
        $files->save($this->post([]), 'document', new UploadedFile(Utils::streamFor('old'), 3, UPLOAD_ERR_OK, 'old.txt'));
        $this->form($files)->run(new ServerRequest('GET', '/form'), static fn ($r, $p) => $r);
        self::assertFalse($files->has('document'));
    }
}
