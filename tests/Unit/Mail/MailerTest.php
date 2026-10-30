<?php

declare(strict_types=1);

namespace LSlim\Tests\Unit\Mail;

use LSlim\Mail\Mailer;
use LSlim\Mail\MailerFactory;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    public function testMessageUsesTheDefaultSenderAndNamedRecipient(): void
    {
        $mailer = new Mailer('utf-8', ['transport' => 'null', 'from' => ['sender@example.test' => '送信者']]);
        $message = $mailer->create('件名', ['recipient@example.test' => '宛先']);
        self::assertSame('件名', $message->getSubject());
        self::assertSame('sender@example.test', $message->getFrom()[0]->getAddress());
        self::assertSame('送信者', $message->getFrom()[0]->getName());
        self::assertSame('recipient@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('宛先', $message->getTo()[0]->getName());
    }

    public function testExplicitSenderOverridesTheDefault(): void
    {
        $mailer = new Mailer('utf-8', ['transport' => 'null', 'from' => 'default@example.test']);
        $message = $mailer->create('subject', 'recipient@example.test', 'override@example.test');
        self::assertSame('override@example.test', $message->getFrom()[0]->getAddress());
    }

    public function testFactoryCreatesAMailerWithTheConfiguredSender(): void
    {
        $factory = new MailerFactory(['transport' => 'null', 'from' => ['sender@example.test' => '送信者']], null);
        self::assertSame('sender@example.test', $factory->getFromAddress());
        self::assertSame('送信者', $factory->getFromName());
        self::assertSame('sender@example.test', $factory->create()->create('subject')->getFrom()[0]->getAddress());
    }
}
