<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Psr\Http\Message\UploadedFileInterface;
use finfo;

class UploadedFileType extends AbstractRule
{
    /**
     * @var string
     */
    public $fileName = '';
    /**
     * @var string
     */
    public $mimeType = '';
    /**
     * @var array
     */
    public $accesptTypes = [];

    /**
     * @param array $accesptTypes
     */
    public function __construct(array $accesptTypes)
    {
        $this->accesptTypes = $accesptTypes;
    }

    public function validate($input)
    {
        if (is_null($input)) {
            return true;
        }

        if (!$input instanceof UploadedFileInterface) {
            return true;
        }

        $this->fileName = $input->getClientFilename();

        if ($input->getError() != UPLOAD_ERR_OK) {
            return true;
        }

        $stream = $input->getStream();
        $tmpFile = $stream->getMetadata('uri');
        $this->mimeType = $this->getMimeType($tmpFile);

        foreach ($this->accesptTypes as $type) {
            if (strpos($type, $this->mimeType) === 0) {
                return true;
            }
        }

        return false;
    }

    private function getMimeType($path)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($path);
    }
}
