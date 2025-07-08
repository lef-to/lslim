<?php

declare(strict_types=1);

namespace LSlim\Validation\Rules;

use Illuminate\Database\ConnectionInterface;
use Respect\Validation\Rules\Core\Simple;

class ExistsInTable extends Simple
{
    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $connection;

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

    public function __construct(ConnectionInterface $connection, $tableName, $fieldName, $callback = null)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->callback = $callback;
    }

    /**
     * @inheritdoc
     */
    public function isValid(mixed $input): bool
    {
        $table = $this->connection
            ->table($this->tableName)
            ->where($this->fieldName, $input);

        if (is_callable($this->callback)) {
            ($this->callback)($table);
        }

        return $table->exists();
    }
}
