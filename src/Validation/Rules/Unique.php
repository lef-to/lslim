<?php
declare(strict_types=1);
namespace LSlim\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Illuminate\Database\Capsule\Manager as Database;

class Unique extends AbstractRule
{
    /**
     * @var \Illuminate\Database\Capsule\Manager
     */
    protected $db;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var string
     */
    protected $connectionName;

    public function __construct(Database $db, $tableName, $fieldName, $callback = null, $connectionName = 'default')
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->callback = $callback;
        $this->connectionName = $connectionName;
    }

    /**
     * @inheritdoc
     */
    public function validate($input)
    {
        $table = $this->db
            ->getConnection($this->connectionName)
            ->table($this->tableName)
            ->where($this->fieldName, $input);

        if (is_callable($this->callback)) {
            ($this->callback)($table);
        }

        return $table->first([ $this->fieldName ]) === null;
    }
}
