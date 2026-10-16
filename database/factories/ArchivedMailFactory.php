<?php

namespace PNScripts\SentfoldLite\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PNScripts\SentfoldLite\Models\ArchivedMail;

/**
 * @extends Factory<ArchivedMail>
 */
class ArchivedMailFactory extends Factory
{
    protected $model = ArchivedMail::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'mailer' => 'smtp',
            'mailable' => null,
            'notification' => null,
            'message_id' => Str::random(24).'@example.test',
            'subject' => 'Test message',
            'from' => [['address' => 'shop@example.test', 'name' => 'Example Shop']],
            'to' => [['address' => 'customer@example.test', 'name' => '']],
            'cc' => [],
            'bcc' => [],
            'reply_to' => [],
            'html' => '<p>Hello</p>',
            'text' => 'Hello',
            'attachments' => [],
            'tags' => [],
            'metadata' => [],
            'redactions' => 0,
            'envelope_only' => false,
            'sent_at' => now(),
        ];
    }
}
