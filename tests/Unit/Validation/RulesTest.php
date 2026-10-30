<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Validation;

use LSlim\Validation\Rules\Hiragana;
use LSlim\Validation\Rules\Timecode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RulesTest extends TestCase
{
    #[DataProvider('timecodes')]
    public function testTimecode(?string $input, bool $expected): void
    {
        self::assertSame($expected, (new Timecode())->isValid($input));
    }

    public static function timecodes(): iterable
    {
        yield 'zero' => ['00:00:00', true];
        yield 'last minute and second' => ['23:59:59', true];
        yield 'duration longer than a day' => ['99:59:59', true];
        yield 'minute overflow' => ['01:60:00', false];
        yield 'second overflow' => ['01:00:60', false];
        yield 'missing leading zero' => ['1:02:03', false];
        yield 'missing seconds' => ['01:02', false];
        yield 'negative duration' => ['-1:02:03', false];
        yield 'surrounding whitespace' => [' 01:02:03 ', false];
        yield 'empty' => ['', false];
        yield 'null' => [null, false];
    }

    #[DataProvider('hiragana')]
    public function testHiragana(?string $input, bool $expected): void
    {
        self::assertSame($expected, (new Hiragana())->isValid($input));
    }

    public static function hiragana(): iterable
    {
        yield 'hiragana' => ['こんにちは', true];
        yield 'small and voiced characters' => ['ぁっがぱゔ', true];
        yield 'iteration marks' => ['さゝさゞ', true];
        yield 'katakana' => ['コンニチハ', false];
        yield 'kanji' => ['日本語', false];
        yield 'mixed alphabet' => ['あa', false];
        yield 'space' => ['あ い', false];
        yield 'empty' => ['', false];
        yield 'null' => [null, false];
    }
}
