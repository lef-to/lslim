<?php
declare(strict_types=1);
namespace LSlim\Monolog\Formatter;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;

class SlackFormatter implements FormatterInterface
{
    protected $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function format(array $record)
    {
        $name = $this->name ?? $record['channel'];

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*' . htmlspecialchars($record['level_name'])
                        . '* message from *' . htmlspecialchars($name) . '*'
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'section',
                'text' => [
                    "type" => "plain_text",
                    "text" => htmlspecialchars($record['message'])
                ]
            ],
        ];

        $ret = [
            'attachments' => [
                'color' => $this->getAttachmentColor($record['level']),
                'blocks' => $blocks
            ]
        ];

        return $ret;
    }

    public function formatBatch(array $records)
    {
        $ret = [];
        foreach ($records as $key => $record) {
            $ret[$key] = $this->format($record);
        }
        return $ret;
    }

    protected function getAttachmentColor(int $level): string
    {
        switch (true) {
            case $level >= Logger::ERROR:
                return 'danger';
            case $level >= Logger::WARNING:
                return 'warning';
            case $level >= Logger::INFO:
                return 'good';
            default:
                return '#e3e4e6';
        }
    }
}
