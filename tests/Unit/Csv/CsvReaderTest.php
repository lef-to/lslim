<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Csv;

use LSlim\Csv\CsvFormatter;
use LSlim\Csv\CsvReader;
use LSlim\Tests\Support\TemporaryFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    use TemporaryFiles;

    public function testReadsQuotedFieldsAndPreservesEmptyRows(): void
    {
        $reader = new CsvReader($this->temporaryFile("名前,備考\r\n\r\n東京,\"a,b\"\r\n大阪,\"say \"\"hello\"\"\"\r\n"));
        self::assertSame(
            [['名前', '備考'], [null], ['東京', 'a,b'], ['大阪', 'say "hello"']],
            iterator_to_array($reader, false)
        );
    }

    #[DataProvider('lineEndings')]
    public function testRowNumbersMatchSpreadsheetRows(string $newline): void
    {
        $contents = '名前,備考' . $newline
            . $newline
            . '東京,"1行目' . $newline . '2行目"' . $newline
            . '大阪,末尾' . $newline
            . $newline;
        $reader = new CsvReader($this->temporaryFile($contents));

        // key + 1 is the spreadsheet row: blank rows count, cell-internal newlines do not.
        self::assertSame([
            0 => ['名前', '備考'],
            1 => [null],
            2 => ['東京', '1行目' . $newline . '2行目'],
            3 => ['大阪', '末尾'],
            4 => [null],
        ], iterator_to_array($reader));
    }

    public static function lineEndings(): iterable
    {
        yield 'LF' => ["\n"];
        yield 'CRLF' => ["\r\n"];
    }

    #[DataProvider('encodedFiles')]
    public function testConvertsInputToUtf8(string $encoding, string $delimiter): void
    {
        $text = "名前{$delimiter}場所\r\nたろう{$delimiter}東京\r\n";
        $contents = match ($encoding) {
            'utf-8' => $text,
            // Generic UTF-16 needs a BOM to distinguish little and big endian files.
            'utf-16' => "\xFF\xFE" . mb_convert_encoding($text, 'UTF-16LE', 'UTF-8'),
            default => mb_convert_encoding($text, $encoding, 'UTF-8'),
        };
        $reader = new CsvReader($this->temporaryFile($contents), $encoding);
        self::assertSame([['名前', '場所'], ['たろう', '東京']], iterator_to_array($reader, false));
    }

    public static function encodedFiles(): iterable
    {
        yield 'UTF-8 CSV' => ['utf-8', ','];
        yield 'Windows CSV' => ['SJIS-win', ','];
        yield 'UTF-16 TSV' => ['utf-16', "\t"];
    }

    public function testReadsAnExplicitDelimiter(): void
    {
        $reader = new CsvReader($this->temporaryFile("名前;場所\nたろう;東京\n"));
        $reader->setDelimmiter(';');
        self::assertSame([['名前', '場所'], ['たろう', '東京']], iterator_to_array($reader, false));
    }

    public function testEmptyFileHasNoRows(): void
    {
        self::assertSame([], iterator_to_array(new CsvReader($this->temporaryFile('')), false));
    }

    public function testFormatterOutputCanBeReadBack(): void
    {
        $rows = [['名前', '備考'], ['たろう', "カンマ,引用\"と改行\nあり"], ['0', '']];
        $contents = implode('', iterator_to_array((new CsvFormatter())->format($rows)));
        self::assertSame($rows, iterator_to_array(new CsvReader($this->temporaryFile($contents)), false));
    }
}
