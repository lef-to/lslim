<?php

declare(strict_types=1);

namespace LSlim\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

final class TimecodeException extends ValidationException
{
    protected $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be valid timecode',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be an timecode',
        ],
    ];
}
