<?php
declare(strict_types=1);
namespace LSlim\Csv;

use IteratorAggregate;
use SplFileObject;
use Traversable;

class CsvReader implements IteratorAggregate
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $charset;

    /**
     * @var string|null
     */
    protected $delimiter = null;

    /**
     * @var string|null
     */
    protected $enclosure = null;

    /**
     * @var string|null
     */
    protected $escape = null;

    public function __construct($path, $charset = 'utf-8')
    {
        $this->path = $path;
        $this->charset = $charset;
    }

    public function setDelimmiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
    }

    public function setEscape($escape)
    {
        $this->escape = $escape;
    }

    public function getIterator(): Traversable
    {
        $filter = ($this->charset == 'utf-8')
            ? $this->path
            : 'php://filter/read=convert.iconv.' . $this->charset . '%2Futf-8/resource=' . $this->path;

        $delimiter = $this->delimiter
            ?? ($this->charset == 'utf-16')
            ? "\t"
            : ",";
        $enclosure = $this->enclosure ?? '"';
        $escape = $this->escape ?? "\\";

        $file = new SplFileObject($filter);
        $file->setCsvControl($delimiter, $enclosure, $escape);
        $file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_AHEAD
        );
        return $file;
    }
}
