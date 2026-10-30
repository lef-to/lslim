<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Mail;

use LSlim\Container;
use LSlim\Mail\Mailer;
use LSlim\Service\MailerProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MailerTest extends TestCase
{
    private const API_URL = 'http://mailpit:8025/api/v1';

    private Mailer $mailer;
    private string $recipient;
    private string $searchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recipient = 'lslim-test-' . bin2hex(random_bytes(16)) . '@example.test';
        $this->searchPath = '/search?' . http_build_query(['query' => 'to:' . $this->recipient]);

        $this->mailer = $this->createMailer('smtp');
    }

    private function createMailer(string $transport): Mailer
    {
        $container = Container::setup('testing');
        $container->register(new MailerProvider([
            'transport' => $transport,
            'host' => 'mailpit',
            'port' => 1025,
            'username' => null,
            'password' => null,
            'options' => ['auto_tls' => false],
            'from' => ['sender@example.test' => '送信者'],
        ]));
        return $container->get('mailer_factory')->create();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->mailer)) {
                $this->mailer->stopTransport();
            }
        } finally {
            try {
                if (isset($this->searchPath)) {
                    // The random recipient scopes deletion to this test, including on assertion failure.
                    $this->request('DELETE', $this->searchPath);
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    public function testSendsJapanesePlainTextThroughTheProviderAndFactory(): void
    {
        $text = "日本語の本文です。\n.ピリオドで始まる行です。\n最後の行です。";
        $message = $this->mailer->create('日本語の送信テスト', [$this->recipient => '受信者'])->text($text);

        $this->mailer->send($message);
        $received = $this->waitForMessage();

        self::assertSame('日本語の送信テスト', $received['Subject']);
        self::assertSame(['Name' => '送信者', 'Address' => 'sender@example.test'], $received['From']);
        self::assertSame([['Name' => '受信者', 'Address' => $this->recipient]], $received['To']);
        // A single-part body receives the final line ending added by SMTP DATA termination.
        self::assertSame($text . "\n", $this->normalizeLineEndings($received['Text']));
        self::assertSame('', $received['HTML']);
        self::assertEmpty($received['Attachments']);
    }

    public function testSendsHtmlAndAnAttachmentWithAnExplicitSender(): void
    {
        $text = "添付ファイルをご確認ください。\n";
        $html = "<p>添付ファイルをご確認ください。</p>\n";
        $attachment = "名前,値\r\n東京,123\r\n";
        $message = $this->mailer->create(
            '添付付きの送信テスト',
            $this->recipient,
            ['override@example.test' => '別の送信者']
        )->text($text)->html($html)->attach($attachment, '資料.csv', 'text/csv');

        $this->mailer->send($message);
        $received = $this->waitForMessage();

        self::assertSame('添付付きの送信テスト', $received['Subject']);
        self::assertSame(['Name' => '別の送信者', 'Address' => 'override@example.test'], $received['From']);
        self::assertSame($this->recipient, $received['To'][0]['Address']);
        self::assertSame($text, $this->normalizeLineEndings($received['Text']));
        self::assertSame($html, $this->normalizeLineEndings($received['HTML']));
        self::assertCount(1, $received['Attachments']);
        $part = $received['Attachments'][0];
        self::assertSame('資料.csv', $part['FileName']);
        self::assertSame('text/csv', $part['ContentType']);
        self::assertSame($attachment, $this->request(
            'GET', '/message/' . rawurlencode($received['ID']) . '/part/' . rawurlencode($part['PartID'])
        ));
    }

    public function testTransportConfigurationControlsWhetherMessagesAreDelivered(): void
    {
        // Both mailers get the same valid SMTP settings; only transport differs.
        $this->mailer = $this->createMailer('null');
        $this->mailer->send($this->mailer->create('null では送信しない', $this->recipient)->text('test body'));
        $this->mailer->stopTransport();

        $result = json_decode($this->request('GET', $this->searchPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $result['messages']);

        // Positive control: the same recipient must receive exactly one message via SMTP.
        $this->mailer = $this->createMailer('smtp');
        $this->mailer->send($this->mailer->create('smtp では送信する', $this->recipient)->text('test body'));
        $received = $this->waitForMessage();
        self::assertSame('smtp では送信する', $received['Subject']);
        self::assertSame($this->recipient, $received['To'][0]['Address']);
        self::assertSame("test body\n", $this->normalizeLineEndings($received['Text']));
    }

    private function waitForMessage(): array
    {
        $deadline = microtime(true) + 5;
        do {
            $result = json_decode($this->request('GET', $this->searchPath), true, flags: JSON_THROW_ON_ERROR);
            if ($result['messages'] !== []) {
                self::assertCount(1, $result['messages']);
                return json_decode(
                    $this->request('GET', '/message/' . rawurlencode($result['messages'][0]['ID'])),
                    true,
                    flags: JSON_THROW_ON_ERROR
                );
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);

        self::fail('Mailpit did not receive a message for ' . $this->recipient . ' within 5 seconds.');
    }

    private function request(string $method, string $path): string
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => 2,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Connection: close\r\n",
        ]]);
        $stream = @fopen(self::API_URL . $path, 'rb', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Cannot connect to the Mailpit API at ' . self::API_URL);
        }
        try {
            $metadata = stream_get_meta_data($stream);
            $body = stream_get_contents($stream);
            $statusLine = $metadata['wrapper_data'][0] ?? '';
            if (!preg_match('/^HTTP\/\S+ (\d{3})/', $statusLine, $match)
                || (int) $match[1] < 200 || (int) $match[1] >= 300 || $body === false) {
                throw new RuntimeException('Mailpit API request failed: ' . $method . ' ' . $path . ' ' . $statusLine);
            }
            return $body;
        } finally {
            fclose($stream);
        }
    }

    private function normalizeLineEndings(string $text): string
    {
        // SMTP uses CRLF; compare text independently of transport line-ending normalization.
        return str_replace("\r\n", "\n", $text);
    }
}
