<?php

declare(strict_types=1);

namespace LSlim\Validation\Rules;

use Psr\Http\Message\UploadedFileInterface;
use Respect\Validation\Rules\Core\Simple;

class UploadedFile extends Simple
{
    public $fileName = '';
    public $errorCode = 0;

    public function isValid(mixed $input): bool
    {
        if (!$input instanceof UploadedFileInterface) {
            return false;
        }

        $this->fileName = $input->getClientFilename();
        $this->errorCode = $input->getError();

        return ($this->errorCode == UPLOAD_ERR_OK);
    }
}
