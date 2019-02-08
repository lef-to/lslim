<?php
declare(strict_types=1);
namespace LSlim\Mail;

use gcrico\SwiftMailerPsrLoggerPlugin\SwiftMailerPsrLoggerPlugin;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LoggerPlugin extends SwiftMailerPsrLoggerPlugin
{
    /**
     * The PSR-3 logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->logger = $logger;
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * @param \Swift_Events_SendEvent $evt
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $evt)
    {
        $this->logger->log(LogLevel::DEBUG, '[MAILER] MESSAGE (beforeSend): ', array());
    }

    /**
     * Invoked immediately after the Message is sent.
     *
     * @param \Swift_Events_SendEvent $evt
     */
    public function sendPerformed(\Swift_Events_SendEvent $evt)
    {
        $result = $evt->getResult();
        $failed_recipients = $evt->getFailedRecipients();
        $message = $evt->getMessage();

        if ($result === \Swift_Events_SendEvent::RESULT_SUCCESS) {
            $level = LogLevel::INFO;
        } else {
            $level = LogLevel::ERROR;
        }

        if ($level) {
            $this->logger->log($level, '[MAILER] MESSAGE (sendPerformed): ', array(
                             'result'            => $result,
                             'failed_recipients' => $failed_recipients,
            ));
        }
    }
}
