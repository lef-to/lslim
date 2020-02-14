<?php
declare(strict_types=1);
namespace LSlim\Monolog\Handler;

use LSlim\Monolog\Formatter\SlackFormatter;
use MonoLog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use Exception;
use GuzzleHttp\Client;

class SlackHandler extends AbstractProcessingHandler
{
    protected $url;

    public function __construct($url, $level = Logger::ERROR, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->url = $url;
    }

    protected function write(array $record): void
    {
        $message = $record['formatted'];

        try {
            $client = $this->createClient();
            $client->request('POST', $this->url, [ 'json' => $message ]);
        } catch (Exception $ex) {
            error_log('Failed to send log to Slack: ' . $ex->getMessage());
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new SlackFormatter();
    }

    protected function createClient(): Client
    {
        return new Client();
    }
}
