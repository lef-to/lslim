<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Csv;

use LSlim\Csv\CsvFormatter;
use PHPUnit\Framework\TestCase;

final class CsvFormatterTest extends TestCase
{
    public function testQuotesDelimitersQuotesAndLineBreaks(): void
    {
        $formatter = new CsvFormatter();
        self::assertSame(
            "plain,\"a,b\",\"say \"\"hello\"\"\",\"line\nbreak\",\"line\rbreak\"\r\n",
            $formatter->formatLine(['plain', 'a,b', 'say "hello"', "line\nbreak", "line\rbreak"])
        );
    }

    public function testKeepsZeroAndEmptyFields(): void
    {
        self::assertSame("0,,0,\r\n", (new CsvFormatter())->formatLine([0, null, '0', '']));
    }

    public function testCustomDelimiterAndLineEnding(): void
    {
        $formatter = new CsvFormatter('UTF-8', ';', "\n");
        self::assertSame("\"a;b\";a,b\n", $formatter->formatLine(['a;b', 'a,b']));
    }

    public function testConvertsJapaneseToWindowsEncoding(): void
    {
        $formatter = new CsvFormatter('SJIS-win');
        self::assertSame(
            mb_convert_encoding("名前,東京\r\n", 'SJIS-win', 'UTF-8'),
            $formatter->formatLine(['名前', '東京'])
        );
    }

    public function testFormatsIterableRowsAndObjects(): void
    {
        $rows = (static function (): iterable {
            yield ['array', 1];
            yield (object) ['name' => 'object', 'value' => 2];
            yield new class {
                public function toArray(): array
                {
                    return ['toArray', 3];
                }
            };
        })();
        self::assertSame(
            ["array,1\r\n", "object,2\r\n", "toArray,3\r\n"],
            iterator_to_array((new CsvFormatter())->format($rows))
        );
    }

    public function testChangingCharsetAndDelimiterUpdatesFormatting(): void
    {
        $formatter = new CsvFormatter('SJIS-win', ',');
        $formatter->setCharset('UTF-8')->setDelimiter('|')->setLineFeed("\n");
        self::assertSame("\"あ|い\"|う\n", $formatter->formatLine(['あ|い', 'う']));
    }
}
