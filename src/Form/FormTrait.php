<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Exception;

trait FormTrait
{
    /**
     * @var array
     */
    protected $data = [];

    abstract protected function getLogger(): LoggerInterface;

    protected function processForm(
        RequestInterface $request,
        ResponseInterface $response,
        callable $action,
        $formName = null
    ): ResponseInterface {
        if ($formName === null) {
            $uri = $request->getUri();
            $basePath = $request->getAttribute('basePath') ?? '';
            $formName = $basePath . $uri->getPath();
        }

        $sessionKey = $this->data[Option::SESSION_KEY] ?? '__form';
        /** @var \LSlim\Form\UploadedFileManagerInterface $fileManager */
        $fileManager = $this->data[Option::UPLOADED] ?? null;

        if (!isset($this->data[Option::PHASE_NAME])) {
            $this->data[Option::PHASE_NAME] = '__form_phase';
        }

        if ($request->getMethod() === 'POST') {
            $body = (array)$request->getParsedBody();
            if (!isset($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = [];
            }

            if (!isset($this->data[Option::BACK_NAME])) {
                $this->data[Option::BACK_NAME] = '__form_back';
            }

            $phase = $body[$this->data[Option::PHASE_NAME]] ?? '';
            if ($phase === 'confirm' || $phase === '') {
                $validator = $this->data[Option::VALIDATOR] ?? null;
                if (is_callable($validator)) {
                    $validator = $validator($request);
                    $this->data[Option::VALIDATOR] = $validator;
                }

                if ($validator !== null) {
                    $validator->validateRequest($request);
                    $this->data[Option::INPUT] = $validator->getParams();

                    if ($fileManager !== null) {
                        $files = $validator->getFiles();
                        foreach ($files as $k => $file) {
                            $fileManager->save($request, $k, $file);
                        }
                    }

                    if ($validator->isValid()) {
                        if ($phase == 'confirm') {
                            $_SESSION[$sessionKey][$formName] = [ 'input' => $this->data[Option::INPUT] ];
                            return $action($response, Phase::CONFIRM);
                        }
                    } else {
                        $this->getLogger()->notice(
                            $formName . ' :validation failed.',
                            [
                                'params' => array_map(function ($err) {
                                    return array_keys($err);
                                }, $validator->getErrors())
                            ]
                        );

                        return $action($response, Phase::INPUT);
                    }
                } else {
                    $this->data[Option::INPUT] = [];
                    if ($phase == 'confirm') {
                        $_SESSION[$sessionKey][$formName] = [];
                        return $action($response, Phase::CONFIRM);
                    }
                }
            }

            if ($phase == 'confirmed' || $phase === '') {
                if ($phase == 'confirmed') {
                    $input = isset($_SESSION[$sessionKey][$formName]['input'])
                        ? $_SESSION[$sessionKey][$formName]['input']
                        : null;

                    if (isset($body[$this->data[Option::BACK_NAME]]) || $input === null) {
                        unset($_SESSION[$sessionKey][$formName]);
                        if ($input !== null) {
                            $_SESSION[$sessionKey][$formName] = [ 'flash' => $input ];
                        }

                        return $response
                            ->withHeader('Location', (string)$request->getUri())
                            ->withStatus(302);
                    }

                    $this->data[Option::INPUT] = $input;
                }

                try {
                    $response = $action($response, Phase::COMPLETE);
                    if ($fileManager !== null) {
                        $fileManager->clear();
                    }
                    return $response;
                } catch (Exception $ex) {
                    $this->getLogger()->error(
                        $formName . ' :process error.',
                        [ 'exception' => $ex ]
                    );

                    $this->data[Option::EXCEPTION] = $ex;

                    if ($phase === 'confirmed') {
                        return $action($response, Phase::CONFIRM);
                    }

                    return $action($response, Phase::INPUT);
                }
            }
        } else {
            $input = (isset($_SESSION[$sessionKey][$formName]['flash']))
                ? $_SESSION[$sessionKey][$formName]['flash']
                : null;

            if (isset($_SESSION[$sessionKey][$formName])) {
                unset($_SESSION[$sessionKey][$formName]);
            }

            if ($input !== null) {
                $this->data[Option::INPUT] = $input;
            } else {
                if ($fileManager) {
                    $fileManager->clear();
                }
            }
        }

        return $action($response, Phase::INPUT);
    }
}
