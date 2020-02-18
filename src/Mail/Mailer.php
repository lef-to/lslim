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
use InvalidArgumentException;

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
     * @var string character set
     */
    private $charset;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    private $logger = null;

    /**
     * @var array
     */
    private $lastFailedRecipients;

    /**
     * constructor
     * @param string $charset
     * @param array $config
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct($charset, array $config, LoggerInterface $logger = null)
    {
        $this->charset = $charset;

        $transport = new Swift_SmtpTransport(
            $config['host'],
            $config['port'],
            $config['security'] ?? null
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
        $charset = $this->charset;
        static::setCharset($charset);

        $message = (new Swift_Message())
            ->setCharset($charset)
            ->setMaxLineLength(0)
            ->setSubject($subject);

        if ($charset == "iso-2022-jp") {
            $message->setEncoder(new PlainContentEncoder('7bit'));
        }

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

    public function stopTransport()
    {
        $this->mailer->getTransport()->stop();
    }

    /**
     * @return array
     */
    public function getLastFailedRecipients()
    {
        return $this->lastFailedRecipients;
    }

    public function getDefaultFromAddress()
    {
        return $this->defaultFrom;
    }

    private static function setCharset($charset)
    {
        $container = Swift_DependencyContainer::getInstance();
        $currentCharset = $container->lookup('properties.charset');

        if (strcasecmp($charset, $currentCharset) === 0) {
            return;
        }

        if ($charset == 'iso-2022-jp') {
            $container
                ->register('mime.qpheaderencoder')
                ->asAliasOf('mime.base64headerencoder');
        } elseif ($charset == 'utf-8') {
            $container
                ->register('mime.qpheaderencoder')
                ->asNewInstanceOf('Swift_Mime_HeaderEncoder_QpHeaderEncoder')
                ->withDependencies(['mime.charstream']);
        } else {
            throw new InvalidArgumentException("Unsupported charset: " . $charset);
        }

        $preference = Swift_Preferences::getInstance();
        $preference->setCharset($charset);
    }
}
