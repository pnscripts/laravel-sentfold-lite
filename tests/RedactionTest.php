<?php

namespace PNScripts\SentfoldLite\Tests;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PNScripts\SentfoldLite\Models\ArchivedMail;
use PNScripts\SentfoldLite\Support\Redactor;
use PNScripts\SentfoldLite\Tests\Fixtures\OrderShipped;
use PNScripts\SentfoldLite\Tests\Fixtures\WelcomeNotification;

class RedactionTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function defaults(): array
    {
        return [
            'password colon' => ['Your password: hunter2 is set', 'Your password: [redacted] is set'],
            'password equals' => ['pwd=s3cret&x=1', 'pwd=[redacted]&x=1'],
            'password in html' => ['<p>Password: <strong>Xy-9!</strong></p>', '<p>Password: <strong>[redacted]</strong></p>'],
            'bulgarian' => ['Вашата парола: тайна123 е активна', 'Вашата парола: [redacted] е активна'],
            'bulgarian capital' => ['Парола = 0000', 'Парола = [redacted]'],
            'token in link' => ['<a href="https://x.test/verify?token=abc.DEF-123&id=5">', '<a href="https://x.test/verify?token=[redacted]&id=5">'],
            'signed url' => ['https://x.test/a?expires=1&signature=f00ba4', 'https://x.test/a?expires=1&signature=[redacted]'],
            'api key' => ['?api_key=sk_live_123', '?api_key=[redacted]'],
            'reset link' => ['https://x.test/reset-password/7a9bC0d?email=a', 'https://x.test/reset-password/[redacted]?email=a'],
            'bearer' => ['Authorization: Bearer eyJhbGciOi.J9.x-y_z=', 'Authorization: Bearer [redacted]'],
            'nothing to hide' => ['Order 1042 shipped', 'Order 1042 shipped'],
            'word containing pass' => ['Your passport number', 'Your passport number'],
            'compound word' => ['mypassword: x', 'mypassword: x'],
        ];
    }

    #[DataProvider('defaults')]
    public function test_default_patterns(string $input, string $expected): void
    {
        $redactor = new Redactor(config('sentfold-lite.redact'));
        $this->assertSame($expected, $redactor->apply($input));
    }

    public function test_redactor_counts_replacements(): void
    {
        $redactor = new Redactor(config('sentfold-lite.redact'));
        $redactor->apply('password: a, pwd=b');
        $redactor->apply('?token=c');
        $this->assertSame(3, $redactor->count());
        $this->assertNull($redactor->apply(null));
    }

    public function test_invalid_pattern_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Redactor(['/unclosed(' => 'x']);
    }

    public function test_subject_and_bodies_are_redacted_before_storing(): void
    {
        Notification::route('mail', 'new@example.test')->notify(new WelcomeNotification);

        $mail = ArchivedMail::sole();
        $stored = json_encode($mail->getAttributes());
        $this->assertStringNotContainsString('Xy7-pass', $stored);
        $this->assertStringNotContainsString('abc123', $stored);
        $this->assertStringContainsString('password: [redacted]', $mail->text);
        $this->assertStringContainsString('token=[redacted]&amp;id=5', $mail->html);
        $this->assertGreaterThanOrEqual(3, $mail->redactions);

        // The recipient got the real message.
        $sent = $this->sentMessages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Xy7-pass', $sent->getTextBody());
    }

    public function test_custom_patterns(): void
    {
        config(['sentfold-lite.redact' => ['/\bBG\d{3}\b/' => '[tracking]', '/Order \d+/' => 'Order #']]);
        Mail::to('ana@example.test')->send(new OrderShipped);

        $mail = ArchivedMail::sole();
        $this->assertSame('Your order 1042 has shipped', $mail->subject);
        $this->assertSame('<h1>Order # shipped</h1><p>Track it with code [tracking].</p>', $mail->html);
        $this->assertSame(4, $mail->redactions);
    }

    public function test_redaction_can_be_turned_off(): void
    {
        config(['sentfold-lite.redact' => []]);
        Mail::raw('password: open', fn (Message $m) => $m->to('a@example.test')->subject('token=1'));
        $this->assertSame('password: open', ArchivedMail::sole()->text);
        $this->assertSame(0, ArchivedMail::sole()->redactions);
    }

    public function test_a_broken_pattern_does_not_stop_the_mail(): void
    {
        config(['sentfold-lite.redact' => ['/broken(' => 'x']]);
        Mail::raw('hello', fn (Message $m) => $m->to('a@example.test')->subject('Hi'));

        $this->assertCount(1, $this->sentMessages());
        $this->assertSame(0, ArchivedMail::count());
    }
}
