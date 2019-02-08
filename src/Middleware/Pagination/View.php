<?php
declare(strict_types=1);
namespace LSlim\Middleware\Pagination;

use Illuminate\Contracts\View\View as ViewInterface;
use BadMethodCallException;

class View implements ViewInterface
{
    private $view;
    private $name;
    private $data;
    private $mergeData;

    public function __construct($view, $name, $data, $mergeData)
    {
        $this->view = $view;
        $this->name = $name;
        $this->data = $data;
        $this->mergeData = $mergeData;
    }

    public function name()
    {
        return $this->name;
    }

    public function render()
    {
        return $this->view->fetch($this->name, $this->data);
    }

    public function with($key, $value = null)
    {
        throw new BadMethodCallException('Method with is not implemented.');
    }
}
