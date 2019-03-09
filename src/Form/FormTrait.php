<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;
use Slim\Exception\SlimException;
use Slim\Flash\Messages as Flash;
use Exception;

trait FormTrait
{
    abstract protected function getLogger(): LoggerInterface;
    abstract protected function getFlash(): Flash;

    protected function processForm(
        Request $req,
        Response $res,
        array $data,
        callable $action,
        $formName = null
    ): ResponseInterface {
        if ($formName === null) {
            $uri = $req->getUri();
            $formName = ($uri instanceof Uri)
                ? $uri->getBasePath() . "/" . $uri->getPath()
                : $uri->getPath();
        }

        $sessionKey = $data[Option::SESSION_KEY] ?? '__form';
        $fileManager = $data[Option::UPLOADED] ?? null;

        if (!isset($data[Option::PHASE_NAME])) {
            $data[Option::PHASE_NAME] = '__form_phase';
        }

        if ($req->isPost()) {
            if (!isset($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = [];
            }

            if (!isset($data[Option::BACK_NAME])) {
                $data[Option::BACK_NAME] = '__form_back';
            }

            $phase = $req->getParsedBodyParam($data[Option::PHASE_NAME], '');
            if ($phase == 'confirm' || $phase === '') {
                $validator = $data[Option::VALIDATOR] ?? null;
                if (is_callable($validator)) {
                    $validator = $validator($req, $data);
                    $data[Option::VALIDATOR] = $validator;
                }

                if ($validator !== null) {
                    $validator->validateRequest($req);
                    $data[Option::INPUT] = $validator->getParams();

                    if ($fileManager !== null) {
                        $files = $validator->getFiles();
                        foreach ($files as $k => $file) {
                            $fileManager->save($req, $k, $file);
                        }
                    }

                    if ($validator->isValid()) {
                        if ($phase == 'confirm') {
                            $_SESSION[$sessionKey][$formName] = $data[Option::INPUT];
                            return $action($res, $data, Phase::CONFIRM);
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
                        return $action($res, $data, Phase::INPUT);
                    }
                } else {
                    $data[Option::INPUT] = [];
                    if ($phase == 'confirm') {
                        $_SESSION[$sessionKey][$formName] = [];
                        return $action($res, $data, Phase::CONFIRM);
                    }
                }
            }

            if ($phase == 'confirmed' || $phase === '') {
                if ($phase == 'confirmed') {
                    $input = $_SESSION[$sessionKey][$formName] ?? null;

                    if ($req->getParsedBodyParam($data[Option::BACK_NAME], null) !== null) {
                        if ($input !== null) {
                            unset($_SESSION[$sessionKey][$formName]);
                            $this->getFlash()->addMessage($formName, $input);
                        }
                        return $res->withRedirect($req->getUri());
                    }
                    if ($input === null) {
                        return $res->withRedirect($req->getUri());
                    }

                    $data[Option::INPUT] = $input;
                }

                try {
                    $res = $action($res, $data, Phase::COMPLETE);
                    if ($fileManager !== null) {
                        $fileManager->clear();
                    }
                    return $res;
                } catch (SlimException $ex) {
                    if ($fileManager !== null) {
                        $fileManager->clear();
                    }
                    throw $ex;
                } catch (Exception $ex) {
                    $this->getLogger()->error(
                        $formName . ' :process error.',
                        [ 'exception' => $ex ]
                    );
                    $data[Option::EXCEPTION] = $ex;

                    if ($phase === 'confirmed') {
                        return $action($res, $data, Phase::CONFIRM);
                    }

                    return $action($res, $data, Phase::INPUT);
                }
            }
        } else {
            if (isset($_SESSION[$sessionKey][$formName])) {
                unset($_SESSION[$sessionKey][$formName]);
            }

            $input = $this->getFlash()->getFirstMessage($formName);
            if ($input !== null) {
                $data[Option::INPUT] = $input;
            } else {
                if ($fileManager) {
                    $fileManager->clear();
                }
            }
        }

        return $action($res, $data, Phase::INPUT);
    }
}
