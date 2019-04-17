<?php
declare(strict_types=1);
namespace LSlim\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;
use Slim\Views\Twig as View;
use Slim\Flash\Messages as Flash;
use LSlim\Validation\Validator;
use LSlim\Traits\HasContainer;

class Controller
{
    use HasContainer;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return \Slim\Views\Twig
     */
    protected function getView(): View
    {
        $view = $this->container->get('view');
        if (!isset($view['csrf']) && $this->container->has('csrf')) {
            $csrf = $this->container->get('csrf');
            $name = $csrf->getTokenName();
            if ($name !== null) {
                $view['csrf'] = [
                    'key' => [
                        'name'  => $csrf->getTokenNameKey(),
                        'value' => $csrf->getTokenValueKey()
                    ],
                    'name'  => $name,
                    'value' => $csrf->getTokenValue()
                ];
            }
        }
        return $view;
    }

    protected function getFlash(): Flash
    {
        return $this->container->get('flash');
    }

    /**
     * @return \LSlim\Validation\Validator
     */
    protected function getValidator(): Validator
    {
        return $this->container->get('validator');
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $res
     * @param string $name template name
     * @param array $data
     */
    protected function render(ResponseInterface $res, $name, array $data = []): ResponseInterface
    {
        $view = $this->getView();
        return $view->render($res, $name . '.twig', $data);
    }

    /**
     * @param \Slim\Http\Response $res
     * @param string $path
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function redirect(Response $res, $path): ResponseInterface
    {
        return $res->withRedirect($path);
    }

    /**
     * @param \Slim\Http\Response $res
     * @param string $name
     * @param array $data
     * @param array $queryParams
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function redirectTo(Response $res, $name, $data = [], $queryParams = []): ResponseInterface
    {
        $path = $this->container->get('router')->pathFor($name, $data, $queryParams);
        return $this->redirect($res, $path);
    }
}
