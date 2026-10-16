<?php

namespace PNScripts\SentfoldLite\Tests;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PNScripts\SentfoldLite\Archiver;
use PNScripts\SentfoldLite\Models\ArchivedMail;
use PNScripts\SentfoldLite\Tests\Fixtures\OrderShipped;
use PNScripts\SentfoldLite\Tests\Fixtures\WelcomeNotification;
use RuntimeException;

class ArchiveTest extends TestCase
{
    public function test_a_mailable_is_archived_with_everything(): void
    {
        Mail::to(new \Illuminate\Mail\Mailables\Address('ana@example.test', 'Ana Petrova'))
            ->cc('boss@example.test')
            ->bcc('audit@example.test')
            ->send(new OrderShipped);

        $this->assertSame(1, ArchivedMail::count());
        $mail = ArchivedMail::first();

        $this->assertSame('Your order 1042 has shipped', $mail->subject);
        $this->assertSame(OrderShipped::class, $mail->mailable);
        $this->assertNull($mail->notification);
        $this->assertSame('array', $mail->mailer);
        $this->assertSame([['address' => 'shop@example.test', 'name' => 'Example Shop']], $mail->from);
        $this->assertSame([['address' => 'ana@example.test', 'name' => 'Ana Petrova']], $mail->to);
        $this->assertSame([['address' => 'boss@example.test', 'name' => '']], $mail->cc);
        $this->assertSame([['address' => 'audit@example.test', 'name' => '']], $mail->bcc);
        $this->assertSame([['address' => 'help@example.test', 'name' => 'Help desk']], $mail->reply_to);
        $this->assertSame('<h1>Order 1042 shipped</h1><p>Track it with code BG123.</p>', $mail->html);
        $this->assertSame('Order 1042 shipped. Track it with code BG123.', trim($mail->text));
        $this->assertSame(['orders', 'shipping'], $mail->tags);
        $this->assertSame(['order_id' => '1042'], $mail->metadata);
        $this->assertSame([[
            'name' => 'invoice-1042.pdf',
            'type' => 'application/pdf',
            'size' => 16,
            'inline' => false,
            'path' => null,
        ]], $mail->attachments);
        $this->assertNotEmpty($mail->message_id);
        $this->assertSame(0, $mail->redactions);
        $this->assertFalse($mail->envelope_only);
        $this->assertTrue($mail->sent_at->isToday());
        $this->assertSame('OrderShipped', $mail->sourceName());
        $this->assertSame(['ana@example.test', 'boss@example.test', 'audit@example.test'], $mail->recipientAddresses());
    }

    public function test_notifications_are_archived_with_their_class(): void
    {
        Notification::route('mail', 'new@example.test')->notify(new WelcomeNotification);

        $mail = ArchivedMail::sole();
        $this->assertSame(WelcomeNotification::class, $mail->notification);
        $this->assertNull($mail->mailable);
        $this->assertSame('Welcome aboard', $mail->subject);
        $this->assertStringContainsString('Confirm', $mail->html);
    }

    public function test_raw_mail_is_archived(): void
    {
        Mail::raw('Plain body only', fn (Message $m) => $m->to('x@example.test')->subject('Raw'));

        $mail = ArchivedMail::sole();
        $this->assertSame('Raw', $mail->subject);
        $this->assertSame('Plain body only', $mail->text);
        $this->assertNull($mail->html);
        $this->assertNull($mail->mailable);
    }

    public function test_excluded_classes_are_not_archived(): void
    {
        config(['sentfold-lite.except' => [OrderShipped::class]]);

        Mail::to('ana@example.test')->send(new OrderShipped);
        Notification::route('mail', 'new@example.test')->notify(new WelcomeNotification);

        $this->assertSame(1, ArchivedMail::count());
        $this->assertSame(WelcomeNotification::class, ArchivedMail::first()->notification);
        $this->assertCount(2, $this->sentMessages(), 'excluded mail is still sent');
    }

    public function test_password_resets_are_archived_as_envelope_only_by_default(): void
    {
        ResetPassword::createUrlUsing(fn () => 'https://example.test/reset-password/SECRET123?email=ana%40example.test');

        Notification::route('mail', 'ana@example.test')->notify(new ResetPassword('SECRET123'));

        $mail = ArchivedMail::sole();
        $this->assertTrue($mail->envelope_only);
        $this->assertSame(ResetPassword::class, $mail->notification);
        $this->assertMatchesRegularExpression('/^Reset/i', $mail->subject); // Laravel 12 and 13 word it differently
        $this->assertNull($mail->html);
        $this->assertNull($mail->text);
        $this->assertSame([['address' => 'ana@example.test', 'name' => '']], $mail->to);
        $this->assertStringNotContainsString('SECRET123', json_encode($mail->getAttributes()));
        ResetPassword::createUrlUsing(null);
    }

    public function test_html_or_text_can_be_left_out(): void
    {
        config(['sentfold-lite.store_html' => false]);
        Mail::to('ana@example.test')->send(new OrderShipped);
        $this->assertNull(ArchivedMail::sole()->html);
        $this->assertNotNull(ArchivedMail::sole()->text);
    }

    public function test_long_bodies_are_cut(): void
    {
        config(['sentfold-lite.max_body_kb' => 1]);
        Mail::to('ana@example.test')->send(new OrderShipped('<p>'.str_repeat('Щ', 3000).'</p>', 'short'));

        $html = ArchivedMail::sole()->html;
        $this->assertStringEndsWith('[Sentfold Lite: body cut at 1 KB]', $html);
        $this->assertTrue(mb_check_encoding($html, 'UTF-8'), 'cut on a character boundary');
        $this->assertLessThan(1100, strlen($html));
    }

    public function test_disabled_archive_stores_nothing(): void
    {
        config(['sentfold-lite.enabled' => false]);
        Mail::to('ana@example.test')->send(new OrderShipped);

        $this->assertSame(0, ArchivedMail::count());
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_a_failing_archive_never_breaks_sending(): void
    {
        $this->app->instance(Archiver::class, new class extends Archiver
        {
            public function archive(\Symfony\Component\Mime\Email $email, array $data = [], ?string $messageId = null): ?ArchivedMail
            {
                throw new RuntimeException('database is down');
            }
        });
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (RuntimeException $e) use (&$reported) {
            $reported[] = $e->getMessage();

            return false;
        });

        Mail::to('ana@example.test')->send(new OrderShipped);

        $this->assertCount(1, $this->sentMessages());
        $this->assertSame(0, ArchivedMail::count());
        $this->assertSame(['database is down'], $reported);
    }

    public function test_custom_table_name(): void
    {
        $this->assertSame('sentfold_lite_messages', (new ArchivedMail)->getTable());
        config(['sentfold-lite.table' => 'mail_log']);
        $this->assertSame('mail_log', (new ArchivedMail)->getTable());
    }

    public function test_search_by_recipient(): void
    {
        ArchivedMail::factory()->create(['to' => [['address' => 'Ana@Example.test', 'name' => '']]]);
        ArchivedMail::factory()->create(['to' => [['address' => 'bob@example.test', 'name' => '']], 'cc' => [['address' => 'ana@example.test', 'name' => '']]]);
        ArchivedMail::factory()->create(['to' => [['address' => 'eve@example.test', 'name' => '']]]);

        $this->assertSame(2, ArchivedMail::query()->sentTo('ana@example')->count());
        $this->assertSame(0, ArchivedMail::query()->sentTo('100%')->count());
    }

    public function test_address_formatting(): void
    {
        $this->assertSame(
            'Ana Petrova <ana@example.test>, office@example.test',
            ArchivedMail::formatAddresses([['address' => 'ana@example.test', 'name' => 'Ana Petrova'], ['address' => 'office@example.test', 'name' => '']])
        );
        $this->assertSame('', ArchivedMail::formatAddresses(null));
    }
}
