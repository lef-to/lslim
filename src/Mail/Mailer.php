<?php
declare(strict_types=1);
namespace LSlim\Mail;

use Swift;
use Swift_DependencyContainer;
use Swift_Preferences;
use Swift_SmtpTransport;
use Swift_Mailer;
use Swift_Plugins_LoggerPlugin;
use Swift_Plugins_Loggers_ArrayLogger;
use Swift_Message;
use Swift_Mime_ContentEncoder_PlainContentEncoder as PlainContentEncoder;
use Psr\Log\LoggerInterface;

class Mailer
{
    /**
     * @var \Swift_Mailer mail transport
     */
    private $mailer = null;

    /**
     * @var array default from address
     */
    private $defaultFrom;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger = null;

    /**
     * @var array
     */
    private $lastFailedRecipients;

    /**
     * @param string $tmpDir
     */
    public static function init($tmpDir)
    {
        Swift::init(function () use ($tmpDir) {
            Swift_DependencyContainer::getInstance()
                ->register('mime.qpheaderencoder')
                ->asAliasOf('mime.base64headerencoder');
            $pref = Swift_Preferences::getInstance()
                ->setCharset('iso-2022-jp');
            if (isset($tmpDir) && is_writeable($tmpDir)) {
                $pref->setTempDir($tmpDir);
            }
        });
    }

    /**
     * constructor
     * @param array $config
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(array $config, LoggerInterface $logger = null)
    {
        $transport = new Swift_SmtpTransport(
            $config['host'],
            $config['port'],
            $config['security']
        );

        if (isset($config['username'])) {
            $transport->setUsername($config['username']);
        }

        if (isset($config['password'])) {
            $transport->setPassword($config['password']);
        }

        if (isset($config['ssl'])) {
            $transport->setStreamOptions(['ssl' => $config['ssl']]);
        }

        $mailer = new Swift_Mailer($transport);

        if (is_null($logger)) {
            $plugin = new Swift_Plugins_LoggerPlugin(new Swift_Plugins_Loggers_ArrayLogger());
        } else {
            $plugin = new LoggerPlugin($logger);
        }
        $mailer->registerPlugin($plugin);

        $this->mailer = $mailer;
        $this->defaultFrom = $config['from'] ?? null;
        $this->logger = $logger;
        $this->lastFailedRecipients = [];
    }

    /**
     * create message
     * @param string $subject
     * @param array|string|null $to
     * @param array|string|null $from
     * @return \Swift_Message
     */
    public function create($subject, $to = null, $from = null)
    {
        $message = (new Swift_Message())
            ->setCharset('iso-2022-jp')
            ->setEncoder(new PlainContentEncoder('7bit'))
            ->setMaxLineLength(0)
            ->setSubject($subject);

        if (!is_null($to)) {
            $message->setTo($to);
        }

        if (is_null($from)) {
            if (isset($this->defaultFrom)) {
                $message->setFrom($this->defaultFrom);
            }
        } else {
            $message->setFrom($from);
        }

        return $message;
    }

    /**
     * send message
     * @param \Swift_Message $message
     * @return int
     */
    public function send($message)
    {
        $this->lastFailedRecipients = [];
        $ret = $this->mailer->send($message, $this->lastFailedRecipients);
        if (!empty($this->lastFailedRecipients)) {
            if (is_null($this->logger)) {
                foreach ($this->lastFailedRecipients as $e) {
                    error_log('Failed to send mail to ' . $e);
                }
            } else {
                $this->logger->error(
                    'Failed to send mail.',
                    [ 'recipients' => $this->lastFailedRecipients ]
                );
            }
        }

        return $ret;
    }

    /**
     * @return array
     */
    public function getLastFailedRecipients()
    {
        return $this->lastFailedRecipients;
    }
}
