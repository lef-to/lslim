<?php
declare(strict_types=1);
namespace LSlim\Misc;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use LSlim\Response\ResponseBuilder;
use Psr\Http\Message\UploadedFileInterface;
use Exception;

class JQueryFileUploadHandler
{
    /**
     * @var \Psr\Http\Message\UploadedFileFactoryInterface
     */
    protected $uploadedFileFactory;

    /**
     * @var \Psr\Http\Message\StreamFactoryInterface
     */
    protected $streamFactory;

    /**
     * @var string
     */
    protected $rootPath;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    public function __construct(
        UploadedFileFactoryInterface $uploadedFileFactoryInterface,
        StreamFactoryInterface $streamFactoryInterface,
        $rootPath,
        ?LoggerInterface $logger = null
    ) {
        $this->uploadedFileFactory = $uploadedFileFactoryInterface;
        $this->streamFactory = $streamFactoryInterface;
        $this->rootPath = $rootPath;
        $this->logger = $logger;
    }

    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $name,
        callable $action
    ): ResponseInterface {
        $files = $request->getUploadedFiles();
        /** @var \Psr\Http\Message\UploadedFileInterface $file */
        $file = $files[$name] ?? null;

        if ($file === null) {
            $this->log(LogLevel::ERROR, $request, $name, 'No uploaded file.');
            return $this->executeActionWithError($request, $response, $name, UPLOAD_ERR_NO_FILE, 400, $action);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->log(
                LogLevel::ERROR,
                $request,
                $name,
                'Upload failed.',
                [ 'error' => $file->getError() ]
            );
            return $this->executeActionWithError($request, $response, $name, $file->getError(), 400, $action);
        }

        $start = null;
        $end = null;
        $size = null;

        $rangeHeader = $request->getHeaderLine('Content-Range');
        if (!empty($rangeHeader)) {
            $range = preg_split('/[^0-9]+/', $rangeHeader);
            if (count($range) < 4 || !is_numeric($range[1]) || !is_numeric($range[2]) || !is_numeric($range[3])) {
                $this->log(
                    LogLevel::ERROR,
                    $request,
                    $name,
                    'Invalaid content-range header.',
                    [ 'header' => $rangeHeader]
                );
                return (new ResponseBuilder($response, $this->streamFactory))
                    ->writeJson([])
                    ->get()
                    ->withStatus(400);
            }

            if ((int)$range[3] < 0) {
                $this->log(
                    LogLevel::ERROR,
                    $request,
                    $name,
                    'File size is overflowed.',
                    [ 'size' => $range[3] ]
                );
                return (new ResponseBuilder($response, $this->streamFactory))
                    ->writeJson([])
                    ->get()
                    ->withStatus(400);
            }

            $start = (int)$range[1];
            $end = (int)$range[2];
            $size = (int)$range[3];
        }

        $path = $this->makeFilePath($request, $name);

        if ($start === null) {
            try {
                $this->moveFile($file, $path);
                return $this->executeAction(
                    $request,
                    $response,
                    $name,
                    $file,
                    200,
                    $action
                );
            } catch (Exception $ex) {
                $this->log(
                    LogLevel::ERROR,
                    $request,
                    $name,
                    'Failed to move file.',
                    [
                        'path' => $path,
                        'exception' => $ex
                    ]
                );

                return $this->executeActionWithError(
                    $request,
                    $response,
                    $name,
                    UPLOAD_ERR_CANT_WRITE,
                    500,
                    $action
                );
            }
        }

        if ($start === 0) {
            $this->deleteFile($path);
        } else {
            $size = $this->getFileSize($path);
            if ($size != $start) {
                $this->log(
                    LogLevel::ERROR,
                    $request,
                    $name,
                    'File data is not continued.',
                    [
                        'start' => $start,
                        'size' => $size
                    ]
                );

                return $this->executeActionWithError(
                    $request,
                    $response,
                    $name,
                    UPLOAD_ERR_PARTIAL,
                    400,
                    $action
                );
            }
        }

        if (!$this->appendFile($path, $file)) {
            $this->log(
                LogLevel::ERROR,
                $request,
                $name,
                'Failed to append data.',
                [ 'path' => $path ]
            );

            return $this->executeActionWithError(
                $request,
                $response,
                $name,
                UPLOAD_ERR_CANT_WRITE,
                500,
                $action
            );
        }

        $currentSize = $this->getFileSize($path);

        if ($size == $currentSize) {
            $stream = $this->createStream($path);
            $uploadedFile = $this->uploadedFileFactory->createUploadedFile(
                $stream,
                $currentSize,
                UPLOAD_ERR_OK,
                $file->getClientFilename(),
                $file->getClientMediaType()
            );

            return $this->executeAction(
                $request,
                $response,
                $name,
                $uploadedFile,
                200,
                $action
            );
        }

        return (new ResponseBuilder($response, $this->streamFactory))
            ->writeJson([])
            ->get()
            ->withStatus(200);
    }

    protected function createStream($path)
    {
        return $this->streamFactory->createStreamFromFile($path, "rb");
    }

    protected function appendFile($path, UploadedFileInterface $file)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fp = fopen($path, "ab");
        if ($fp === null) {
            return false;
        }

        $stream = $file->getStream();
        while (!$stream->eof()) {
            $buf = $stream->read(8192);
            if (fwrite($fp, $buf) === false) {
                fclose($fp);
                return false;
            }
        }

        fclose($fp);
        return true;
    }

    protected function makeFilePath(ServerRequestInterface $request, $name)
    {
        return rtrim($this->rootPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $name;
    }

    protected function deleteFile($path)
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function moveFile(UploadedFileInterface $file, $path)
    {
        return $file->moveTo($path);
    }

    protected function getFileSize($path)
    {
        return filesize($path);
    }

    protected function executeActionWithError(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $name,
        $uploadError,
        $statusCode,
        callable $action
    ): ResponseInterface {
        $stream = $this->streamFactory->createStream();
        $file = $this->uploadedFileFactory->createUploadedFile(
            $stream,
            null,
            $uploadError
        );
        return $this->executeAction($request, $response, $name, $file, $statusCode, $action);
    }

    protected function executeAction(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $name,
        UploadedFileInterface $file,
        $statusCode,
        callable $action
    ): ResponseInterface {
        try {
            $res = $action($name, $file);
            if ($res instanceof ResponseInterface) {
                return $res;
            }
        } catch (Exception $ex) {
            $this->log(
                LogLevel::ERROR,
                $request,
                $name,
                'Failed to execute action.',
                [ 'exception' => $ex ]
            );
            return (new ResponseBuilder($response, $this->streamFactory))
                ->writeJson([])
                ->get()
                ->withStatus(500);
        }

        return (new ResponseBuilder($response, $this->streamFactory))
            ->writeJson([])
            ->get()
            ->withStatus($statusCode);
    }

    protected function log($level, ServerRequestInterface $request, $name, $message, $context = [])
    {
        $context = array_merge(
            [
                'name' => $name,
                'url' => $request->getUri()->getPath()
            ],
            $context
        );

        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        } else {
            error_log($message . ' ' . json_encode($context));
        }
    }
}
