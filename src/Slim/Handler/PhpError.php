<?php
declare(strict_types=1);

namespace LSlim\Slim\Handler;

use Slim\Handlers\PhpError as BaseHandler;
use Psr\Container\ContainerInterface;

class PhpError extends BaseHandler
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
    protected function renderHtmlErrorMessage(\Throwable $error)
    {
        if (!$this->displayErrorDetails && $this->templateName && $this->container->has('view')) {
            $view = $this->container->get('view');
            return $view->fetch($this->templateName, [ 'error' => $error ]);
        }
        return parent::renderHtmlErrorMessage($error);
    }
}
