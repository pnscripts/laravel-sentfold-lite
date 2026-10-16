<?php

namespace PNScripts\SentfoldLite\Tests\Fixtures;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShipped extends Mailable
{
    public function __construct(public string $bodyHtml = '<h1>Order 1042 shipped</h1><p>Track it with code BG123.</p>', public string $plain = 'Order 1042 shipped. Track it with code BG123.')
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('shop@example.test', 'Example Shop'),
            replyTo: [new Address('help@example.test', 'Help desk')],
            subject: 'Your order 1042 has shipped',
            tags: ['orders', 'shipping'],
            metadata: ['order_id' => '1042'],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->bodyHtml, text: 'mail.plain', with: ['plain' => $this->plain]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => '%PDF-1.4 invoice', 'invoice-1042.pdf')->withMime('application/pdf'),
        ];
    }
}
