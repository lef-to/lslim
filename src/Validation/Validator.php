<?php
declare(strict_types=1);
namespace Lslim\Validation;

use Respect\Validation\Validator as RespectValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface as Logger;
use Illuminate\Support\Arr;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Exceptions\NestedValidationException;
use RuntimeException;
use Exception;

class Validator
{
    const TYPE_FILE = 'file';
    const TYPE_BOOL = 'bool';
    const OPTION_TYPE = 'type';
    const OPTION_DEFAULT = 'default';
    const OPTION_KEY = 'key';
    const OPTION_ASSERT = 'assert';
    const OPTION_TRIM = 'trim';
    const OPTION_EMPTY_TO_NULL = 'empty_to_null';

    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var array
     */
    protected $rules;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    protected $logger;

    /**
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(Logger $logger = null)
    {
        $this->logger = $logger;
        $this->params = [];
        $this->files = [];
        $this->rules = [];
        $this->errors = [];
    }

    protected function fixValue($value, array $option)
    {
        $type = $option[static::OPTION_TYPE] ?? '';
        $trim = $option[static::OPTION_TRIM] ?? true;

        if (is_array($value)) {
            return array_map(function ($v) use ($option) {
                return $this->fixValue($v, $option);
            }, $value);
        }
      
        if ($trim && $value !== null) {
            $value = trim($value);
        }

        if ($value === '') {
            $emptyToNull = $option[static::OPTION_EMPTY_TO_NULL] ?? true;
            if ($emptyToNull) {
                $value = null;
            }
        }

        if ($value === null && isset($option[static::OPTION_DEFAULT])) {
            $value = $option[static::OPTION_DEFAULT];
        }

        if ($type == static::TYPE_BOOL) {
            $value = ($value) ? true : false;
        }

        return $value;
    }

    public function validateRequest(Request $req)
    {
        $params = $req->getParsedBody();
        if ($params === null) {
            $params = [];
        } elseif (is_object($params)) {
            $params = get_object_vars($params);
        }

        $files = $req->getUploadedFiles();

        foreach ($this->rules as $rule) {
            $name = $rule['name'];
            $value = null;

            $option = $rule['option'];
            $type = $option[static::OPTION_TYPE] ?? '';
            $key = $option[static::OPTION_KEY] ?? [];

            if (empty($key)) {
                if ($type == static::TYPE_FILE) {
                    $value = Arr::get($files, $name, null);
                    if ($value instanceof UploadedFileInterface && $value->getError() == UPLOAD_ERR_NO_FILE) {
                        $value = null;
                    }
                    Arr::set($this->files, $name, $value);
                } else {
                    $value = $this->fixValue(Arr::get($params, $name, null), $option);
                    Arr::set($this->params, $name, $value);
                }
            } else {
                $skip = false;
                if (is_array($key)) {
                    $value = [];
                    foreach ($key as $k) {
                        if ($this->hasError($k)) {
                            $skip = true;
                        } else {
                            if (array_key_exists($k, $this->params)) {
                                $value[] = Arr::get($this->params, $k);
                            } elseif (array_key_exists($k, $this->files)) {
                                $value[] = Arr::get($this->files, $k);
                            } else {
                                throw new RuntimeException($k . ' is not validated.');
                            }
                        }
                    }
                } else {
                    if (array_key_exists($key, $this->params)) {
                        $value = Arr::get($this->params, $key);
                    } elseif (array_key_exists($k, $this->files)) {
                        $value = Arr::get($this->files, $key);
                    } else {
                        throw new RuntimeException($key . ' is not validated.');
                    }
                }

                if ($skip) {
                    continue;
                }
            }

            try {
                if ($option[static::OPTION_ASSERT] ?? false) {
                    $rule['rule']->assert($value);
                } else {
                    $rule['rule']->check($value);
                }
            } catch (ValidationException $ex) {
                // ファイルの場合はバリデーションがとおらなかったものは削除する
                if ($type == static::TYPE_FILE) {
                    if (is_array($key)) {
                        foreach ($key as $k) {
                            Arr::set($this->files, $k, null);
                        }
                    } else {
                        Arr::set($this->files, $key, null);
                    }
                }
                $this->setError($name, $ex);
            } catch (Exception $ex) {
                if ($this->logger !== null) {
                    $this->logger->error(
                        'Failed to validation.',
                        [
                            'name' => $name,
                            'exception' => $ex
                        ]
                    );
                }
                throw $ex;
            }
        }
    }

    /**
     * @param string $name
     * @param \Respect\Validation\Exceptions\ValidationException $ex
     */
    protected function setError($name, ValidationException $ex)
    {
        if ($ex instanceof NestedValidationException) {
            $iter = $ex->getIterator();
            if ($iter->count()) {
                foreach ($iter as $e) {
                    $this->setError($name, $e);
                }
                return;
            }
        }

        if (!isset($this->errors[$name])) {
            $this->errors[$name] = [];
        }

        $id = $ex->guessId();
        $this->errors[$name][$id] = $ex;
    }

    /**
     * @param string $name
     * @param \Respect\Validation\Validator $rule
     * @param array $option
     * @return $this
     */
    public function add($name, RespectValidator $rule, $option = [])
    {
        $this->rules[] = [
            'name' => $name,
            'rule' => $rule,
            'option' => $option
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !$this->isValid();
    }

    /**
     * @param string $name
     * @param string|null $key
     * @return bool
     */
    public function hasError($name, $key = null)
    {
        if (!isset($this->errors[$name])) {
            return false;
        }

        if (!is_null($key)) {
            return isset($this->errors[$name][$key]);
        }

        return true;
    }

    /**
     * @param string $name
     * @return array
     */
    public function getError($name)
    {
        if (isset($this->errors[$name])) {
            return $this->errors[$name];
        }

        return [];
    }
}
