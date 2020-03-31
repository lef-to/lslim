<?php
declare(strict_types=1);
namespace LSlim\Mail;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swift_Events_SendEvent as SendEvent;
use Swift_Events_SendListener as SendListener;
use Swift_Events_CommandEvent as CommandEvent;
use Swift_Events_CommandListener as CommandListener;
use Swift_Events_ResponseEvent as ResponseEvent;
use Swift_Events_ResponseListener as ResponseListener;
use Swift_Events_TransportChangeEvent as TransportChangeEvent;
use Swift_Events_TransportChangeListener as TransportChangeListener;
use Swift_Events_TransportExceptionEvent as TransportExceptionEvent;
use Swift_Events_TransportExceptionListener as TransportExceptionListener;

class LoggerPlugin implements
    SendListener,
    CommandListener,
    ResponseListener,
    TransportChangeListener,
    TransportExceptionListener
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $logs;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logs = [];
    }

    protected function log($level, $message, array $context = [])
    {
        $this->logger->log($level, '[MAILER] ' . $message, $context);
    }

    protected function dump($level)
    {
        if (!empty($this->logs)) {
            $this->log($level, implode(PHP_EOL, $this->logs));
            $this->logs = [];
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeSendPerformed(SendEvent $evt)
    {
        $this->dump(LogLevel::DEBUG);
    }

    /**
     * @inheritdoc
     */
    public function sendPerformed(SendEvent $evt)
    {
        $result = $evt->getResult();
        $message = $evt->getMessage();

        switch ($result) {
            case SendEvent::RESULT_SUCCESS:
                $level = LogLevel::INFO;
                $result = 'SUCCESS';
                break;
            case SendEvent::RESULT_TENTATIVE:
                $level = LogLevel::WARNING;
                $result = 'TENTATIVE';
                break;
            case SendEvent::RESULT_PENDING:
                $level = LogLevel::WARNING;
                $result = 'PENDING';
                break;
            case SendEvent::RESULT_SPOOLED:
                $level = LogLevel::INFO;
                $result = 'SPOOLED';
                break;
            case SendEvent::RESULT_FAILED:
                $level = LogLevel::ERROR;
                $result = 'FAILED';
                break;
            default:
                $level = LogLevel::CRITICAL;
                $result = 'UNKNOWN';
                break;
        };

        $context = [
            'subject' => $message->getSubject(),
        ];

        $item = $message->getTo();
        if (!empty($item)) {
            $context['to'] = $item;
        }

        $item = $message->getCc();
        if (!empty($item)) {
            $context['cc'] = $item;
        }

        $item = $message->getBcc();
        if (!empty($item)) {
            $context['bcc'] = $item;
        }

        $item = $evt->getFailedRecipients();
        if (!empty($item)) {
            $context['failed_recipients'] = $item;
        }

        $this->log($level, 'Send result: ' . $result, $context);

        if (!empty($this->logs)) {
            if ($level === LogLevel::INFO) {
                $level = LogLevel::DEBUG;
            }
            array_unshift($this->logs, '-- Send performed');
            $this->dump($level);
        }
    }

    /**
     * @inheritdoc
     */
    public function commandSent(CommandEvent $evt)
    {
        $this->logs[] = '>> ' . trim($evt->getCommand());
    }

    /**
     * @inheritdoc
     */
    public function responseReceived(ResponseEvent $evt)
    {
        $this->logs[] = '<< ' . trim($evt->getResponse());
    }

    /**
     * @inheritdoc
     */
    public function beforeTransportStarted(TransportChangeEvent $evt)
    {
        $this->dump(LogLevel::DEBUG);
        $transportName = get_class($evt->getTransport());
        $this->logs[] = '++ Starting ' . $transportName;
    }

    /**
     * @inheritdoc
     */
    public function transportStarted(TransportChangeEvent $evt)
    {
        $transportName = get_class($evt->getTransport());
        $this->logs[] = '++ ' . $transportName . ' started';
    }

    /**
     * @inheritdoc
     */
    public function beforeTransportStopped(TransportChangeEvent $evt)
    {
        $transportName = get_class($evt->getTransport());
        $this->logs[] = '++ Stopping ' . $transportName;
    }

    /**
     * @inheritdoc
     */
    public function transportStopped(TransportChangeEvent $evt)
    {
        $transportName = get_class($evt->getTransport());
        $this->logs[] = '++ ' . $transportName . ' stopped';
        $this->dump(LogLevel::DEBUG);
    }

    /**
     * @inheritdoc
     */
    public function exceptionThrown(TransportExceptionEvent $evt)
    {
        $e = $evt->getException();
        $message = sprintf('!! %s (code: %s)', $e->getMessage(), $e->getCode());

        if (!empty($this->logs)) {
            $message .= PHP_EOL . implode(PHP_EOL, $this->logs) . PHP_EOL;
            $this->logs = [];
        }

        $this->logger->error($message, [ 'exception' => $e ]);
    }
}
