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

    public function create($charset = null): Mailer
    {
        $charset = $charset ?? $this->config['charset'] ?? 'iso-2022-jp';
        $klass = $this->config['mailer'] ?? Mailer::class;
        return new $klass($charset, $this->config, $this->logger);
    }

    public function getFromAddress()
    {
        $from = $this->config['from'] ?? [];
        if (empty($from)) {
            return null;
        }

        if (is_array($from)) {
            return array_keys($from)[0];
        }

        return $from;
    }

    public function getFromName()
    {
        $from = $this->config['from'] ?? [];
        if (is_array($from) && !empty($from)) {
            return array_values($from)[0];
        }

        return null;
    }
}
