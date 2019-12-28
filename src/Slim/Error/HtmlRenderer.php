<?php
declare(strict_types=1);
namespace LSlim\Slim\Error;

use Psr\Container\ContainerInterface;
use Slim\Exception\HttpNotFoundException;
use Throwable;

class HtmlRenderer
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        /** @var \Slim\Views\Twig $view */
        $view = $this->container->get('view');

        $template = $this->getTemplateName($exception);
        return $view->fetch($template, [
            'exception' => $exception,
            'display_error_details' => $displayErrorDetails
        ]);
    }

    protected function getTemplateName(Throwable $exception)
    {
        if ($exception instanceof HttpNotFoundException) {
            return '404.twig';
        }

        return '500.twig';
    }
}
