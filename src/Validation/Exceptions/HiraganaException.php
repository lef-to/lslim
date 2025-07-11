<?php

declare(strict_types=1);

namespace LSlim\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class HiraganaException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} contains non-hiragana character.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} contains hiragana character.',
        ]
    ];
}
