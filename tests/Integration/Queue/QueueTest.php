<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Queue;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;
use LSlim\Console\BaseApplication;
use LSlim\Service\HasQueue;
use LSlim\Service\IlluminateQueueProvider;
use LSlim\Service\IlluminateCacheProvider;
use LSlim\Service\IlluminateRedisProvider;
use LSlim\Tests\Support\MySqlTestCase;
use LSlim\Tests\Support\Queue\QueueState;
use LSlim\Tests\Support\Queue\RecordingJobHandler;
use LSlim\Tests\Support\Queue\RecordingQueueableJob;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Tester\CommandTester;

final class QueueTest extends MySqlTestCase
{
    private QueueManager $manager;
    private Worker $worker;
    private QueueState $state;
    private TestHandler $logs;
    private string $queueName;
    private string $table;
    private string $failedTable;
    private BaseApplication $application;
    private array $failedEvents = [];
    private mixed $originalTestNow;
    private mixed $originalFacadeApplication;

    protected function configureContainer(): void
    {
        $this->originalTestNow = Carbon::getTestNow();
        $this->originalFacadeApplication = Facade::getFacadeApplication();
        $this->queueName = 'lslim-test-' . bin2hex(random_bytes(8));
        $this->table = 'lslim_jobs_' . bin2hex(random_bytes(8));
        $this->failedTable = 'lslim_failed_' . bin2hex(random_bytes(8));
        $this->state = new QueueState();
        $this->logs = new TestHandler();
        $this->container['logger'] = new Logger('queue-test', [$this->logs]);
        $this->container[QueueState::class] = $this->state;
        $this->container['base_dir'] = dirname(__DIR__, 3);
        $this->container['var_dir'] = sys_get_temp_dir();
        $this->container['config_dir'] = sys_get_temp_dir();
        $this->container->register(new IlluminateRedisProvider([
            'client' => 'phpredis',
            'default' => [
                'host' => getenv('REDIS_HOST') ?: 'redis',
                'port' => getenv('REDIS_PORT') ?: 6379,
                'database' => 15,
            ],
        ]));
        $this->container->register(new IlluminateCacheProvider([
            'default' => 'array', 'stores' => ['array' => ['driver' => 'array']],
        ]));
        $this->container->register(new IlluminateQueueProvider([
            'default' => 'database',
            'connections' => [
                'database' => [
                    'driver' => 'database', 'connection' => 'default', 'table' => $this->table,
                    'queue' => $this->queueName, 'retry_after' => 60,
                ],
                'redis' => [
                    'driver' => 'redis', 'connection' => 'default',
                    'queue' => $this->queueName, 'retry_after' => 60, 'block_for' => null,
                ],
            ],
            'failed' => ['driver' => 'database', 'database' => 'default', 'table' => $this->failedTable],
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection->statement(
            "CREATE TEMPORARY TABLE `{$this->table}` ("
            . 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, queue VARCHAR(255) NOT NULL, '
            . 'payload LONGTEXT NOT NULL, attempts TINYINT UNSIGNED NOT NULL, '
            . 'reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED NOT NULL, created_at INT UNSIGNED NOT NULL'
            . ') ENGINE=InnoDB'
        );
        $this->connection->statement(
            "CREATE TEMPORARY TABLE `{$this->failedTable}` ("
            . 'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, connection TEXT NOT NULL, '
            . 'queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at TIMESTAMP NOT NULL'
            . ') ENGINE=InnoDB'
        );

        // Use LSlim's console bootstrap and real worker, including event and bus bindings.
        $this->application = new BaseApplication('queue-test', '1.0', $this->container);
        $this->manager = $this->container->get('queue');
        $laravel = $this->container->get('laravel');
        $this->worker = $laravel->make('queue.worker');
        $laravel->make('events')->listen(JobFailed::class, function (JobFailed $event): void {
            $this->failedEvents[] = $event;
        });
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->manager)) {
                // clear() only removes this test's ready, reserved, delayed and notification keys.
                $redisQueue = $this->manager->connection('redis');
                $redisQueue->clear($this->queueName);
                $redisQueue->clear($this->queueName . '-other');
                $this->container->get('redis')->connection()->disconnect();
            }
        } finally {
            Carbon::setTestNow($this->originalTestNow ?? null);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->originalFacadeApplication ?? null);
            parent::tearDown();
        }
    }

    public static function drivers(): iterable
    {
        yield 'MySQL' => ['database'];
        yield 'Redis' => ['redis'];
    }

    #[DataProvider('drivers')]
    public function testPushesThroughHasQueueAndDeletesAfterHandling(string $driver): void
    {
        $data = ['message' => '日本語のジョブ'];
        $this->pusher()->push(RecordingJobHandler::class, $data, $this->queueName, $driver);
        $queue = $this->manager->connection($driver);
        self::assertSame(1, $queue->size());
        $job = $queue->pop();
        self::assertNotNull($job);
        self::assertSame(1, $job->attempts());
        $job->fire();
        self::assertSame([$data], $this->state->handled);
        self::assertTrue($job->isDeleted());
        self::assertSame(0, $queue->size());
        self::assertNull($queue->pop());
    }

    #[DataProvider('drivers')]
    public function testReservedJobCannotBePoppedAgainUntilReleased(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $queue->push(RecordingJobHandler::class, ['message' => 'reserve']);
        $job = $queue->pop();
        self::assertNotNull($job);
        self::assertNull($queue->pop());
        $job->release(0);
        self::assertTrue($job->isReleased());
        $retried = $queue->pop();
        self::assertNotNull($retried);
        self::assertSame(2, $retried->attempts());
        $retried->delete();
        self::assertSame(0, $queue->size());
    }

    #[DataProvider('drivers')]
    public function testExpiredReservationCanBeRetried(string $driver): void
    {
        Carbon::setTestNow(Carbon::now());
        $queue = $this->manager->connection($driver);
        $queue->push(RecordingJobHandler::class, ['message' => 'recover']);
        $reserved = $queue->pop();
        self::assertNotNull($reserved);
        self::assertNull($queue->pop());
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $retried = $queue->pop();
        self::assertNotNull($retried);
        self::assertSame(2, $retried->attempts());
        $retried->fire();
        self::assertSame([['message' => 'recover']], $this->state->handled);
        self::assertSame(0, $queue->size());
    }

    #[DataProvider('drivers')]
    public function testNamedQueuesAreIsolated(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $other = $this->queueName . '-other';
        $queue->push(RecordingJobHandler::class, ['message' => 'other'], $other);
        self::assertSame(0, $queue->size());
        self::assertNull($queue->pop());
        self::assertSame(1, $queue->size($other));
        $job = $queue->pop($other);
        self::assertNotNull($job);
        $job->fire();
        self::assertSame([['message' => 'other']], $this->state->handled);
        self::assertSame(0, $queue->size($other));
    }

    #[DataProvider('drivers')]
    public function testDelayedJobsBecomeAvailableAfterTheirDelay(string $driver): void
    {
        Carbon::setTestNow(Carbon::now());
        $data = ['message' => 'delayed'];
        $this->pusher()->later(60, RecordingJobHandler::class, $data, $this->queueName, $driver);
        $queue = $this->manager->connection($driver);
        self::assertSame(1, $queue->size());
        self::assertNull($queue->pop());
        Carbon::setTestNow(Carbon::now()->addSeconds(61));
        $job = $queue->pop();
        self::assertNotNull($job);
        $job->fire();
        self::assertSame([$data], $this->state->handled);
        self::assertSame(0, $queue->size());
    }

    #[DataProvider('drivers')]
    public function testJobHandlerCanReleaseItselfWithoutBeingDeleted(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $data = ['action' => 'release'];
        $queue->push(RecordingJobHandler::class, $data);
        $job = $queue->pop();
        self::assertNotNull($job);
        $job->fire();
        self::assertTrue($job->isReleased());
        self::assertFalse($job->isDeleted());
        self::assertSame(1, $queue->size());
        $retried = $queue->pop();
        self::assertNotNull($retried);
        self::assertSame(2, $retried->attempts());
        $retried->fire();
        self::assertTrue($retried->isDeleted());
        self::assertSame([$data, $data], $this->state->handled);
        self::assertSame(0, $queue->size());
    }

    #[DataProvider('drivers')]
    public function testJobHandlerExceptionIsLoggedAndFailsImmediately(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $queue->push(RecordingJobHandler::class, ['action' => 'fail']);
        $job = $queue->pop();
        self::assertNotNull($job);
        $job->fire();
        self::assertTrue($job->hasFailed());
        self::assertTrue($job->isDeleted());
        self::assertSame(0, $queue->size());
        self::assertTrue($this->logs->hasError('Failed to handle job.'));
        self::assertCount(1, $this->failedEvents);
        self::assertSame('Legacy handler failed.', $this->failedEvents[0]->exception->getMessage());
    }

    #[DataProvider('drivers')]
    public function testWorkerExecutesSerializedQueueableJobs(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $queue->push(new RecordingQueueableJob('serialized 日本語'));
        $this->runWorker($driver);
        self::assertSame([
            ['message' => 'serialized 日本語', 'attempt' => 1, 'environment' => 'testing'],
        ], $this->state->handled);
        self::assertSame(0, $queue->size());
        self::assertSame([], $this->state->failed);
        self::assertFalse($this->logs->hasErrorRecords());
    }

    #[DataProvider('drivers')]
    public function testWorkerRetriesAnExceptionAndThenSucceeds(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $queue->push(new RecordingQueueableJob('retry', 1));
        $this->runWorker($driver);
        self::assertCount(1, $this->state->handled);
        self::assertSame(1, $queue->size());
        self::assertSame([], $this->failedEvents);
        self::assertTrue($this->logs->hasError('Queueable job failed.'));
        $this->runWorker($driver);
        self::assertSame([1, 2], array_column($this->state->handled, 'attempt'));
        self::assertSame([], $this->state->failed);
        self::assertSame([], $this->failedEvents);
        self::assertSame(0, $queue->size());
    }

    #[DataProvider('drivers')]
    public function testWorkerStopsRetryingAtTheJobAttemptLimit(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $queue->push(new RecordingQueueableJob('always fails', 10));
        $this->runWorker($driver);
        $this->runWorker($driver);
        self::assertSame([1, 2], array_column($this->state->handled, 'attempt'));
        self::assertSame(['Queueable job failed.'], $this->state->failed);
        self::assertCount(1, $this->failedEvents);
        self::assertSame($driver, $this->failedEvents[0]->connectionName);
        self::assertSame(0, $queue->size());
        self::assertNull($queue->pop());
    }

    #[DataProvider('drivers')]
    public function testBusDispatcherQueuesInsteadOfExecutingImmediately(string $driver): void
    {
        $command = new RecordingQueueableJob('dispatched');
        $command->connection = $driver;
        $command->queue = $this->queueName;
        $this->container->get('laravel')->make(BusDispatcher::class)->dispatch($command);
        self::assertSame([], $this->state->handled);
        self::assertSame(1, $this->manager->connection($driver)->size());
        $this->runWorker($driver);
        self::assertCount(1, $this->state->handled);
        self::assertSame(0, $this->manager->connection($driver)->size());
    }

    private function runWorker(string $driver): void
    {
        $this->worker->runNextJob($driver, $this->queueName, new WorkerOptions(backoff: 0, sleep: 0, maxTries: 2));
    }

    #[DataProvider('drivers')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCliWorkerStoresFailedJobsAndRetryCommandRequeuesOnlyTheSelectedJob(string $driver): void
    {
        $queue = $this->manager->connection($driver);
        $command = new RecordingQueueableJob('CLI retry', 1);
        $command->tries = 1;
        $queue->push($command);

        // Separate process isolates WorkCommand's static event-listener registration.
        $work = new CommandTester($this->application->find('queue:work'));
        self::assertSame(0, $work->execute([
            'connection' => $driver, '--queue' => $this->queueName,
            '--once' => true, '--sleep' => 0, '--tries' => 1, '--quiet' => true,
        ], ['interactive' => false]));
        self::assertSame(0, $queue->size());
        self::assertSame(1, $this->connection->table($this->failedTable)->count());
        $failed = $this->connection->table($this->failedTable)->first();
        self::assertSame($driver, $failed->connection);
        self::assertSame($this->queueName, $failed->queue);
        self::assertStringContainsString('Queueable job failed.', $failed->exception);
        self::assertStringContainsString('CLI retry', $failed->payload);

        $otherId = $this->connection->table($this->failedTable)->insertGetId([
            'connection' => $driver, 'queue' => $this->queueName . '-other',
            'payload' => $failed->payload, 'exception' => 'unrelated failure', 'failed_at' => $failed->failed_at,
        ]);
        $retry = new CommandTester($this->application->find('queue:retry'));
        self::assertSame(0, $retry->execute(['id' => [(string) $failed->id]], ['interactive' => false]));
        self::assertSame(1, $queue->size());
        self::assertFalse($this->connection->table($this->failedTable)->where('id', $failed->id)->exists());
        self::assertTrue($this->connection->table($this->failedTable)->where('id', $otherId)->exists());

        $this->state->allowSuccess = true;
        $retried = $queue->pop();
        self::assertNotNull($retried);
        self::assertSame(1, $retried->attempts());
        $this->worker->process($driver, $retried, new WorkerOptions(sleep: 0));
        self::assertSame([1, 1], array_column($this->state->handled, 'attempt'));
        self::assertSame(0, $queue->size());
        self::assertSame(1, $this->connection->table($this->failedTable)->count());
    }

    private function pusher(): object
    {
        return new class ($this->manager) {
            use HasQueue;

            public function __construct(private QueueManager $manager)
            {
            }

            protected function getQueue(): QueueManager
            {
                return $this->manager;
            }

            public function push($job, $data, string $queue, string $connection): void
            {
                $this->pushJob($job, $data, $queue, $connection);
            }

            public function later(int $delay, $job, $data, string $queue, string $connection): void
            {
                $this->pushJobLater($delay, $job, $data, $queue, $connection);
            }
        };
    }
}
