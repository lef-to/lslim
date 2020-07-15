<?php
declare(strict_types=1);
namespace Lslim\Session;

use SessionHandlerInterface;
use Psr\Container\ContainerInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Exception;
use SessionUpdateTimestampHandlerInterface;

class DatabaseHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @param \Psr\Container\ContainerInterface $container
     * @param string $tableName
     * @param string $connectionName
     */
    public function __construct(
        ContainerInterface $container,
        $tableName = 'session',
        $connectionName = 'default'
    ) {
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->tableName = $tableName;
    }

    private function table(): QueryBuilder
    {
        return $this->container->get('db')
            ->getConnection($this->connectionName)
            ->table($this->tableName);
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function open($save_path, $session_name): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy($id): bool
    {
        try {
            $this->table()->where('id', $id)->delete();
            return true;
        } catch (Exception $ex) {
            $this->container->get('logger')->error(
                'Failed to destroy session data.',
                [
                    'session_id' => $id,
                    'exception' => $ex
                ]
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        $ts = time() - $maxlifetime;
        $table = $this->table();
        $table->where('ts', '<', $ts)->delete();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function read($session_id)
    {
        $table = $this->table();
        $data = $table->where('id', $session_id)->value('data');

        if (is_null($data)) {
            return '';
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function write($session_id, $session_data)
    {
        try {
            $this->container->get('db')
                ->getConnection($this->connectionName)
                ->transaction(function () use ($session_id, $session_data) {
                    $current = $this->table()->where('id', $session_id)
                        ->lockForUpdate()
                        ->first([ 'ts' ]);

                    $ts = time();
                    if ($current === null) {
                        $this->table()->insert([
                            'id' => $session_id,
                            'data' => $session_data,
                            'ts' => $ts
                        ]);
                    } else {
                        $this->table()
                            ->where('id', $session_id)
                            ->update([
                                'data' => $session_data,
                                'ts' => $ts
                            ]);
                    }
                });

            return true;
        } catch (Exception $ex) {
            $this->container->get('logger')->error(
                'Failed to write session data.',
                [ 'exception' => $ex ]
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function updateTimestamp($session_id, $session_data)
    {
        $ts = time();
        try {
            return $this->table()->where('id', $session_id)->update([ 'ts' => $ts ]) > 0;
        } catch (Exception $ex) {
            $this->container->get('logger')->error(
                'Failed to update timestamp.',
                [ 'exception' => $ex ]
            );
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function validateId($session_id)
    {
        return $this->table()->where('id', $session_id)->exists();
    }
}
