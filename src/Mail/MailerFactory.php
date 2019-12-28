<?php
declare(strict_types=1);
namespace LSlim\Mail;

use Psr\Log\LoggerInterface;

class MailerFactory
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    private $logger;

    public function __construct(array $config, ?LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function create($charset = 'iso-2022-jp'): Mailer
    {
        return new Mailer($charset, $this->config, $this->logger);
    }
}
