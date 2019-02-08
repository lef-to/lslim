<?php
declare(strict_types=1);
namespace Lslim\Validation;

use Respect\Validation\Validator as RespectValidator;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface as File;
use Psr\Log\LoggerInterface as Logger;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Exceptions\NestedValidationException;
use Exception;

class Validator
{
    const TYPE_FILE = 'file';
    const TYPE_BOOL = 'file';
    const OPTION_TYPE = 'type';
    const OPTION_KEY = 'key';
    const OPTION_DO_ASSERT = 'do_assert';

    /**
     * @var array
     */
    protected $values;

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
    }

    protected function fixValue($value, $type)
    {
        if (is_array($value)) {
            return array_map(function ($v) use ($type) {
                $v = $this->fixValue($v, $type);
            }, $value);
        }
        
        if ($value === '') {
            $value = null;
        }

        if ($type == static::TYPE_BOOL) {
            $value = ($value) ? true : false;
        }

        return $value;
    }

    protected function validateBody(Request $req)
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

            $key = $option[static::OPTION_KEY] ?? $name;

            if (is_array($key)) {
                $value = [];
                foreach ($key as $k) {
                    $v = null;
                    if ($type == static::TYPE_FILE) {
                        $v = $files[$k] ?? null;
                    } else {
                        $v = $params[$k] ?? null;
                    }
                    $value[$k] = $v;
                }
            } else {
                if ($type == static::TYPE_FILE) {
                    $value = $files[$key] ?? null;
                } else {
                    $value = $params[$key] ?? null;
                }
            }

            $value = $this->fixValue($value, $type);

            if (isset($option['default']) && ($value === null || (is_array($value) && empty($value)))) {
                $value = $option['default'];
            }

            $assert = $option[static::OPTION_DO_ASSERT] ?? false;
            try {
                if ($assert) {
                    $rule['rule']->assert($value);
                } else {
                    $rule['rule']->check($value);
                }

                if ($type == static::TYPE_FILE) {
                    $this->files[$name] = $value;
                } else {
                    $this->values[$name] = $value;
                }
            } catch (ValidationException $ex) {
                $this->setError($name, $ex);
            } catch (Exception $ex) {
                if ($this->logger !== null) {
                    $this->logger->error(
                        'Failed to validate ' . $name,
                        [ 'exception' => $ex ]
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
    public function getValues()
    {
        return $this->values;
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
