<?php
declare(strict_types=1);
namespace LSlim\Illuminate\Pagination;

use Illuminate\Contracts\View\Factory;
use Slim\Views\Twig;
use BadMethodCallException;

class ViewFactory implements Factory
{
    /**
     * @var \Slim\Views\Twig
     */
    private $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function exists($view)
    {
        throw new BadMethodCallException('Method exists is not implemented.');
    }

    public function file($path, $data = array(), $mergeData = array())
    {
        throw new BadMethodCallException('Method file is not implemented.');
    }

    public function make($view, $data = array(), $mergeData = array())
    {
        return new View($this->view, $view, $data, $mergeData);
    }

    public function share($key, $value = null)
    {
        throw new BadMethodCallException('Method share is not implemented.');
    }

    public function composer($views, $callback, $priority = null)
    {
        throw new BadMethodCallException('Method composer is not implemented.');
    }

    public function creator($views, $callback)
    {
        throw new BadMethodCallException('Method creator is not implemented.');
    }

    public function addNamespace($namespace, $hints)
    {
        throw new BadMethodCallException('Method addNamespace is not implemented.');
    }

    public function replaceNamespace($namespace, $hints)
    {
        throw new BadMethodCallException('Method replaceNamespace is not implemented.');
    }
}
