<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait SimpleFormTrait
{
    use FormTrait;

    abstract protected function renderResponse(ResponseInterface $response, $name, array $data = []);

    protected function processSimpleForm(
        RequestInterface $request,
        ResponseInterface $response,
        $templatePrefix,
        callable $action,
        $formName = null
    ): ResponseInterface {
        return $this->processForm(
            $request,
            $response,
            function (ResponseInterface $res, $phase) use ($action, $templatePrefix) {
                if ($phase == Phase::INPUT) {
                    return $this->renderResponse($res, $templatePrefix . 'input', $this->data);
                }

                if ($phase == Phase::CONFIRM) {
                    return $this->renderResponse($res, $templatePrefix . 'confirm', $this->data);
                }

                $result = $action($res);
                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                return $this->renderResponse($res, $templatePrefix . 'complete', $this->data);
            },
            $formName
        );
    }
}
