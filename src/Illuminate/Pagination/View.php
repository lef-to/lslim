<?php
declare(strict_types=1);
namespace LSlim\Illuminate\Pagination;

use Illuminate\Contracts\View\View as ViewInterface;
use Illuminate\Contracts\Support\Htmlable;
use Slim\Views\Twig;
use BadMethodCallException;

class View implements ViewInterface, Htmlable
{
    /**
     * @var \Slim\Views\Twig
     */
    private $view;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $mergeData;

    public function __construct(Twig $view, $name, $data, $mergeData)
    {
        $this->view = $view;
        $this->name = $name;
        $this->data = $data;
        $this->mergeData = $mergeData;
    }

    /**
     * @inheritdoc
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->view->fetch($this->name, $this->data);
    }

    /**
     * @inheritdoc
     */
    public function with($key, $value = null)
    {
        throw new BadMethodCallException('Method with is not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function toHtml()
    {
        return  $this->render();
    }
}
