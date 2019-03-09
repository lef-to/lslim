<?php
declare(strict_types=1);
namespace LSlim\Slim\Handler;

use Slim\Handlers\NotFound as BaseHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

class NotFound extends BaseHandler
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $templateName;

    public function __construct(ContainerInterface $container, $templateName = null)
    {
        $this->container = $container;
        $this->templateName = $templateName;
    }


    /**
     * @inheritdoc
     */
    protected function renderHtmlNotFoundOutput(ServerRequestInterface $request)
    {
        if ($this->templateName && $this->container->has('view')) {
            $view = $this->container->get('view');
            return $view->fetch($this->templateName, [ 'request' => $request ]);
        }
        return parent::renderHtmlNotFoundOutput($request);
    }
}
