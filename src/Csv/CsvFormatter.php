<?php
declare(strict_types=1);
namespace LSlim\Csv;

use Traversable;

class CsvFormatter
{
    /**
     * @var string
     */
    protected $charset = null;

    /**
     * @var string
     */
    protected $delimiter = null;

    /**
     * @var string
     */
    protected $lineFeed = null;

    /**
     * @var bool
     */
    protected $convert = null;

    /**
     * @var string
     */
    protected $regex = null;

    public function __construct($charset = 'UTF-8', $delimiter = ',', $lineFeed = "\r\n")
    {
        $this->setCharset($charset);
        $this->setDelimiter($delimiter);
        $this->setLineFeed($lineFeed);
    }

    public function setCharset($charset): self
    {
        if ($this->charset != $charset) {
            $this->charset = $charset;
            $this->convert = ($this->charset != 'UTF-8') ? true : false;
        }
        return $this;
    }

    public function setDelimiter($delimiter): self
    {
        if ($this->delimiter != $delimiter) {
            $this->delimiter = $delimiter;
            $this->regex = '/[\r\n"' . preg_quote($this->delimiter, '/') . ']/u';
        }
        return $this;
    }

    public function setLineFeed($lineFeed): self
    {
        $this->lineFeed = $lineFeed;
        return $this;
    }

    public function format(iterable $data): Traversable
    {
        foreach ($data as $line) {
            yield $this->formatLine($line);
        }
    }

    public function formatLine(iterable $data)
    {
        $ret = '';
        $first = true;
        foreach ($data as $v) {
            if (preg_match($this->regex, $v)) {
                $v = str_replace('"', '""', $v);
                $v = '"' . $v . '"';
            }

            if ($this->convert) {
                $v = mb_convert_encoding($v, $this->charset, 'UTF-8');
            }

            if ($first) {
                $ret = $v;
                $first = false;
            } else {
                $ret .= $this->delimiter . $v;
            }
        }
        return $ret . $this->lineFeed;
    }
}
