<?php
declare(strict_types=1);

namespace LSlim\Slim\Handler;

use Slim\Handlers\Error as BaseHandler;
use Psr\Container\ContainerInterface;

class Error extends BaseHandler
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $templateName;

    public function __construct(ContainerInterface $container, $templateName = null, $displayErrorDetails = false)
    {
        parent::__construct($displayErrorDetails);
        $this->container = $container;
        $this->templateName = $templateName;
    }

    /**
     * @inheritdoc
     */
    protected function writeToErrorLog($throwable)
    {
        $logger = $this->container->get('logger');
        $logger->critical('Application error.', [ 'exception' => $throwable ]);
    }

    /**
     * @inheritdoc
     */
    protected function renderHtmlErrorMessage(\Exception $exception)
    {
        if (!$this->displayErrorDetails && $this->templateName && $this->container->has('view')) {
            $view = $this->container->get('view');
            return $view->fetch($this->templateName, [ 'error' => $exception ]);
        }
        return parent::renderHtmlErrorMessage($exception);
    }
}
