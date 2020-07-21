<?php
declare(strict_types=1);
namespace LSlim\Session;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use Psr\Container\ContainerInterface;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Exception;

class RedisHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
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
    private $prefix;

    public function __construct(
        ContainerInterface $container,
        $prefix = '',
        $connectionName = null
    ) {
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->prefix = $prefix;
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
    public function close()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function destroy($id): bool
    {
        $redis = $this->getConnection();

        $key = $this->prefix . $id;

        try {
            $redis->del([ $key ]);
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
    public function read($session_id)
    {
        $redis = $this->getConnection();

        $key = $this->prefix . $session_id;

        $data = $redis->get($key);
        if ($data === null) {
            return '';
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function write($session_id, $session_data)
    {
        $redis = $this->getConnection();
        $expire = $this->getExpire();

        $key = $this->prefix . $session_id;

        try {
            $result = $redis->setex($key, $expire, $session_data);
            if ($result) {
                return true;
            }
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
        $redis = $this->getConnection();
        $expire = $this->getExpire();

        $key = $this->prefix . $session_id;

        try {
            $result = $redis->expire($key, $expire);
            if ($result) {
                return true;
            }
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
        $redis = $this->getConnection();

        $key = $this->prefix . $session_id;
        $result = ($redis->exists($key)) ? true : false;

        // @see https://github.com/symfony/symfony/pull/36490
        if (\PHP_VERSION_ID < 70317 || (70400 <= \PHP_VERSION_ID && \PHP_VERSION_ID < 70405)) {
            // work around https://bugs.php.net/79413
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) as $frame) {
                if (!isset($frame['class']) && isset($frame['function'])) {
                    $fname = $frame['function'];
                    if ($fname === 'session_start') {
                        return $result;
                    }
                    if (\in_array($fname, ['session_regenerate_id', 'session_create_id'], true)) {
                        return !$result;
                    }
                }
            }
        }

        return $result;
    }

    protected function getConnection(): RedisConnection
    {
        return $this->container->get('redis')->connection($this->connectionName);
    }

    protected static function getExpire()
    {
        return (int) max(1, (int) ini_get('session.gc_maxlifetime'));
    }
}
