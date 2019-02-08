<?php
declare(strict_types=1);
namespace Lslim\Session;

use SessionHandlerInterface;
use Illuminate\Database\Capsule\Manager as Database;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Psr\Log\LoggerInterface;
use Exception;

class DatabaseHandler implements SessionHandlerInterface
{
    /**
     * @var \Illuminate\Database\Capsule\Manager;
     */
    private $db;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Illuminate\Database\Capsule\Manager $db
     * @param \Psr\Log\LoggerInterface $logger
     * @param string $tableName
     */
    public function __construct(Database $db, LoggerInterface $logger, $tableName = 'session')
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->tableName = $tableName;
    }

    private function table(): QueryBuilder
    {
        return $this->db->getConnection()->table($this->tableName);
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
        $table = $this->table();
        return ($table->where('id', $id)->delete() > 0);
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
            $this->db->getConnection()->transaction(function () use ($session_id, $session_data) {
                $current = $this->table()->where('id', $session_id)
                    ->lockForUpdate()
                    ->first([ 'ts' ]);

                if ($current === null) {
                    $ts = time();
                    $this->table()->insert([
                        'id' => $session_id,
                        'data' => $session_data,
                        'ts' => $ts
                    ]);
                } else {
                    $this->table()
                        ->where('id', $session_id)
                        ->update([
                            'data' => $session_data
                        ]);
                }
            });
            return true;
        } catch (Exception $ex) {
            $this->logger->error(
                'Failed to write session data.',
                [ 'exception' => $ex ]
            );
        }

        return false;
    }
}
