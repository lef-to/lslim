<?php
declare(strict_types=1);
namespace LSlim\Console\Command\Mail;

use Illuminate\Console\Command;
use Psr\Container\ContainerInterface;

class TestCommand extends Command
{
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $appName;

    /**
     * @var string
     */
    protected $signature = 'mail:test {address : The address.}'
        . '{name : The display name.}';

    /**
     * @var string
     */
    protected $description = 'Send test mail';

    public function __construct(ContainerInterface $container, $appName)
    {
        parent::__construct();
        $this->container = $container;
        $this->appName = $appName;
    }

    public function handle()
    {
        $addr = trim($this->input->getArgument('address'));
        $name = trim($this->input->getArgument('name'));

        $env = $this->container->get('app_mode');
        $mailer = $this->container->get('mailer');
        $message = $mailer->create(
            'Test mail: ' . $this->appName . ' (' . $env . ')',
            [ $addr => $name ]
        );

        $body = <<< EOM
This is a test mail.

Please ignore it.

EOM;

        $message->setBody($body);
        $mailer->send($message);
    }
}
