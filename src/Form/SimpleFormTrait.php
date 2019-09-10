<?php
declare(strict_types=1);
namespace LSlim\Form;

use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface;

trait SimpleFormTrait
{
    use FormTrait;

    abstract protected function render(ResponseInterface $res, $name, array $data = []): ResponseInterface;

    protected function processSimpleForm(
        RequestInterface $req,
        ResponseInterface $res,
        array $data,
        $tmplatePrefix,
        callable $action,
        $formName = null
    ): ResponseInterface {
        return $this->processForm(
            $req,
            $res,
            $data,
            function (ResponseInterface $res, array $data, $phase) use ($tmplatePrefix, $action) {
                if ($phase == Phase::INPUT) {
                    return $this->render($res, $tmplatePrefix . 'input', $data);
                }

                if ($phase == Phase::CONFIRM) {
                    return $this->render($res, $tmplatePrefix . 'confirm', $data);
                }

                $result = $action($res, $data);
                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                return $this->render($res, $tmplatePrefix . 'complete', $data);
            },
            $formName
        );
    }
}
