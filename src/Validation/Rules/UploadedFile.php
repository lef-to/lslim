<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile extends AbstractRule
{
    public $fileName = '';
    public $errorCode = 0;

    public function validate($input)
    {
        if (!$input instanceof UploadedFileInterface) {
            return false;
        }

        $this->fileName = $input->getClientFilename();
        $this->errorCode = $input->getError();

        return ($this->errorCode == UPLOAD_ERR_OK);
    }
}
