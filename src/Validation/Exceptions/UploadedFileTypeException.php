<?php
declare(strict_types=1);
namespace LSlim\Validation\Exceptions;

use \Respect\Validation\Exceptions\ValidationException;

class UploadedFileTypeException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'File type is wrong.',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'File type is correct.',
        ],
    ];
}
