<?php
declare(strict_types=1);
namespace LSlim\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

class ExistsInTableException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must exists in table.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not exists in table.',
        ],
    ];
}
