<?php
declare(strict_types=1);
namespace LSlim\Mail;

use gcrico\SwiftMailerPsrLoggerPlugin\SwiftMailerPsrLoggerPlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swift_Events_SendEvent;

class LoggerPlugin extends SwiftMailerPsrLoggerPlugin
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * @param \Swift_Events_SendEvent $evt
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
        $this->logger->log(LogLevel::DEBUG, '[MAILER] MESSAGE (beforeSend): ', []);
    }

    /**
     * Invoked immediately after the Message is sent.
     *
     * @param \Swift_Events_SendEvent $evt
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $result = $evt->getResult();
        $failed_recipients = $evt->getFailedRecipients();

        if ($result === Swift_Events_SendEvent::RESULT_SUCCESS) {
            $level = LogLevel::INFO;
        } else {
            $level = LogLevel::ERROR;
        }

        if ($level) {
            $this->logger->log($level, '[MAILER] MESSAGE (sendPerformed): ', [
                'result'            => $result,
                'failed_recipients' => $failed_recipients,
            ]);
        }
    }
}
