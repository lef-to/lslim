<?php
declare(strict_types=1);
namespace LSlim\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailer
{
    /**
     * @var TransportInterface
     */
    private $transport = null;

    /**
     * @var SymfonyMailer mailer
     */
    private $mailer = null;

    /**
     * @var array default from address
     */
    private $defaultFrom;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    private $logger = null;

    /**
     * constructor
     * @param string $charset
     * @param array $config
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct($charset, array $config, LoggerInterface $logger = null)
    {
        $transportType = $config['transport'] ?? 'smtp';

        if ($transportType == 'null') {
            $transport = Transport::fromDsn('null://null', null, null, $logger);
        } else {
            $dsn = new Dsn(
                'smtp',
                $config['host'],
                $config['username'],
                $config['password'],
                $config['port'],
                $config['options'] ?? []
            );
            $factory = new Transport(iterator_to_array(Transport::getDefaultFactories(null, null, $logger)));
            $transport = $factory->fromDsnObject($dsn);
        }

        $mailer = new SymfonyMailer($transport);

        $this->transport    = $transport;
        $this->mailer       = $mailer;
        $this->defaultFrom  = $config['from'] ?? null;
        $this->logger       = $logger;
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
        $message = $this->createMessage()
            ->subject($subject);

        if (!is_null($to)) {
            $message->to($to);
        }

        if (is_null($from)) {
            if (isset($this->defaultFrom)) {
                $key = array_key_first($this->defaultFrom);
                $message->from(new Address($key, $this->defaultFrom[$key]));
            }
        } elseif (is_array($from)) {
            $key = array_key_first($from);
            $message->from(new Address($key, $from[$key]));
        } else {
            $message->from($from);
        }

        return $message;
    }

    public function createMessage(): Email
    {
        return new Email();
    }

    /**
     * send message
     * @param Email $message
     * @return void
     */
    public function send(Email $message)
    {
        $this->mailer->send($message);
    }

    public function stopTransport()
    {
        if ($this->transport instanceof SmtpTransport) {
            $this->transport->stop();
        }
    }

    public function getDefaultFromAddress()
    {
        return $this->defaultFrom;
    }
}
