<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Validation;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use LSlim\Validation\Validator;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Validation\Validator as v;
use RuntimeException;

final class ValidatorTest extends TestCase
{
    #[DataProvider('values')]
    public function testNormalizesValues(mixed $input, array $options, mixed $expected): void
    {
        $validator = (new Validator())->add('value', v::alwaysValid(), $options);
        $validator->validate(['value' => $input]);
        self::assertTrue($validator->isValid());
        self::assertSame(['value' => $expected], $validator->getParams());
    }

    public static function values(): iterable
    {
        yield 'trim' => ['  東京  ', [], '東京'];
        yield 'retain whitespace' => ['  東京  ', ['trim' => false], '  東京  '];
        yield 'empty to null' => ['  ', [], null];
        yield 'retain empty' => ['', ['empty_to_null' => false], ''];
        yield 'default' => [null, ['default' => 'fallback'], 'fallback'];
        yield 'string zero' => ['0', ['default' => 'fallback'], '0'];
        yield 'integer zero' => [0, [], 0];
        yield 'integer value' => [123, [], 123];
        yield 'boolean false' => [false, [], false];
        yield 'bool zero' => ['0', ['type' => 'bool'], false];
        yield 'bool one' => ['1', ['type' => 'bool'], true];
        yield 'bool missing' => [null, ['type' => 'bool'], false];
        yield 'nested values' => [['a' => ' x ', 'b' => ['', '0']], [], ['a' => 'x', 'b' => [null, '0']]];
    }

    public function testValidatesNestedRequestBodyAndIgnoresUnspecifiedFields(): void
    {
        $validator = (new Validator())->add('profile.name', v::stringType()->notEmpty());
        $validator->validateRequest((new ServerRequest('POST', '/'))->withParsedBody([
            'profile' => ['name' => '  東京  '], 'unexpected' => 'ignored',
        ]));
        self::assertSame(['profile' => ['name' => '東京']], $validator->getParams());
        self::assertTrue($validator->isValid());
    }

    public function testAggregatesAssertionErrorsAndUsesAliases(): void
    {
        $validator = (new Validator())
            ->add('name', v::alpha()->length(3, 8), ['assert' => true])
            ->add('email', v::email(), ['alias' => 'invalid_email']);
        $validator->validate(['name' => '1', 'email' => 'invalid']);
        self::assertFalse($validator->isValid());
        self::assertTrue($validator->hasError('name', 'alpha'));
        self::assertTrue($validator->hasError('name', 'length'));
        self::assertTrue($validator->hasError('email', 'invalid_email'));
        self::assertCount(2, $validator->getErrors());
    }

    public function testDependentRuleReceivesValidatedValuesAndSkipsInvalidDependencies(): void
    {
        $calls = [];
        $validator = (new Validator())
            ->add('password', v::stringType()->notEmpty())
            ->add('confirmation', v::stringType()->notEmpty())
            ->add('matching', v::callback(static function ($values) use (&$calls): bool {
                $calls[] = $values;
                return $values[0] === $values[1];
            }), ['key' => ['password', 'confirmation']]);
        $validator->validate(['password' => ' secret ', 'confirmation' => 'other']);
        self::assertSame([['secret', 'other']], $calls);
        self::assertTrue($validator->hasError('matching'));
        $validator->clear();
        $validator->validate(['password' => '', 'confirmation' => 'other']);
        self::assertCount(1, $calls);
        self::assertFalse($validator->hasError('matching'));
        self::assertTrue($validator->hasError('password'));
    }

    public function testDependentRuleRequiresTheReferencedFieldToBeValidatedFirst(): void
    {
        $validator = (new Validator())->add('dependent', v::alwaysValid(), ['key' => 'missing']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing is not validated.');
        $validator->validate([]);
    }

    public function testClearResetsResultsAndKeepsTheRules(): void
    {
        $validator = (new Validator())->add('email', v::email());
        $validator->validate(['email' => 'invalid']);
        self::assertTrue($validator->hasErrors());
        $validator->clear();
        self::assertSame([], $validator->getErrors());
        self::assertSame([], $validator->getParams());
        self::assertSame([], $validator->getFiles());
        $validator->validate(['email' => 'valid@example.test']);
        self::assertTrue($validator->isValid());
    }

    public function testSetReplacesRulesAndUnsetRemovesThem(): void
    {
        $validator = (new Validator())->add('value', v::email())->set('value', v::stringType())->unset('unused');
        $validator->validate(['value' => 'not an email']);
        self::assertTrue($validator->isValid());
        $validator->clear();
        $validator->unset('value')->validate([]);
        self::assertSame([], $validator->getParams());
    }

    public function testInvalidUploadIsExcludedFromValidatedFiles(): void
    {
        $file = new UploadedFile(Utils::streamFor('payload'), 7, UPLOAD_ERR_OK, 'test.txt');
        $validator = (new Validator())->add('document', v::callback(static fn ($value) => false), ['type' => 'file']);
        $validator->validate([], ['document' => $file]);
        self::assertTrue($validator->hasError('document'));
        self::assertSame(['document' => null], $validator->getFiles());
    }

    public function testMissingUploadIsPassedToTheRuleAsNull(): void
    {
        $file = new UploadedFile(Utils::streamFor(''), 0, UPLOAD_ERR_NO_FILE);
        $validator = (new Validator())->add('document', v::nullType(), ['type' => 'file']);
        $validator->validate([], ['document' => $file]);
        self::assertTrue($validator->isValid());
        self::assertSame(['document' => null], $validator->getFiles());
    }

    public function testUnexpectedRuleExceptionIsLoggedAndRethrown(): void
    {
        $logs = new TestHandler();
        $validator = (new Validator(new Logger('test', [$logs])))->add('value', v::callback(static function (): bool {
            throw new RuntimeException('rule failed');
        }));
        try {
            $validator->validate(['value' => 'input']);
            self::fail('Expected a rule exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('rule failed', $exception->getMessage());
            self::assertTrue($logs->hasError('Failed to validation.'));
        }
    }
}
