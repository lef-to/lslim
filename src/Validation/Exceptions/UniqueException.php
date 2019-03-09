<?php
declare(strict_types=1);
namespace LSlim\Validation\Exceptions;

use \Respect\Validation\Exceptions\ValidationException;

class UniqueException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} is duplicated.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} is duplicated.',
        ],
    ];
}
