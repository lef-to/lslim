<?php
declare(strict_types=1);
namespace LSlim\Validation\Exceptions;

use \Respect\Validation\Exceptions\ValidationException;

class UploadedFileException extends ValidationException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'Failed to upload:（{{errorCode}}）',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => 'Succeeded to upload',
        ],
    ];
}
