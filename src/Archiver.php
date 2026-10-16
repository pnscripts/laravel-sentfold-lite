<?php

namespace PNScripts\SentfoldLite;

use Illuminate\Support\Str;
use PNScripts\SentfoldLite\Models\ArchivedMail;
use PNScripts\SentfoldLite\Support\Redactor;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Turns a sent Symfony message into an ArchivedMail row.
 */
class Archiver
{
    public const RESENT_HEADER = 'X-Sentfold-Resent-From';

    /**
     * @param  array<string, mixed>  $data  The data array of Laravel's MessageSent event.
     */
    public function archive(Email $email, array $data = [], ?string $messageId = null): ?ArchivedMail
    {
        $mailable = isset($data['__laravel_mailable']) && is_string($data['__laravel_mailable']) ? $data['__laravel_mailable'] : null;
        $notification = isset($data['__laravel_notification']) && is_string($data['__laravel_notification']) ? $data['__laravel_notification'] : null;

        $except = (array) config('sentfold-lite.except', []);
        if (($mailable !== null && in_array($mailable, $except, true)) || ($notification !== null && in_array($notification, $except, true))) {
            return null;
        }
        $envelopeOnly = ($mailable !== null && in_array($mailable, (array) config('sentfold-lite.envelope_only', []), true))
            || ($notification !== null && in_array($notification, (array) config('sentfold-lite.envelope_only', []), true));

        $redactor = new Redactor((array) config('sentfold-lite.redact', []));
        $uuid = (string) Str::uuid();

        $html = null;
        $text = null;
        if (! $envelopeOnly) {
            $html = config('sentfold-lite.store_html', true) ? $this->limit($redactor->apply($this->body($email->getHtmlBody()))) : null;
            $text = config('sentfold-lite.store_text', true) ? $this->limit($redactor->apply($this->body($email->getTextBody()))) : null;
        }

        $headers = $email->getHeaders();
        $tags = [];
        $metadata = [];
        foreach ($headers->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            } elseif ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            }
        }
        $resentFrom = $headers->has(self::RESENT_HEADER) ? $headers->get(self::RESENT_HEADER)?->getBodyAsString() : null;

        return ArchivedMail::create([
            'uuid' => $uuid,
            'mailer' => isset($data['mailer']) && is_string($data['mailer']) ? $data['mailer'] : null,
            'mailable' => $mailable,
            'notification' => $notification,
            'message_id' => $messageId,
            'subject' => $redactor->apply($email->getSubject()),
            'from' => $this->addresses($email->getFrom()),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'reply_to' => $this->addresses($email->getReplyTo()),
            'html' => $html,
            'text' => $text,
            'attachments' => $this->attachments($email, $uuid, ! $envelopeOnly),
            'tags' => array_values(array_unique($tags)),
            'metadata' => $metadata,
            'redactions' => $redactor->count(),
            'envelope_only' => $envelopeOnly,
            'resent_from' => $resentFrom !== null && Str::isUuid($resentFrom) ? $resentFrom : null,
            'sent_at' => now(),
        ]);
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<array{address: string, name: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $a) => ['address' => $a->getAddress(), 'name' => $a->getName()], array_values($addresses));
    }

    /**
     * @param  resource|string|null  $body
     */
    private function body($body): ?string
    {
        if (is_resource($body)) {
            rewind($body);
            $body = stream_get_contents($body);
        }

        return is_string($body) ? $body : null;
    }

    private function limit(?string $body): ?string
    {
        $max = (int) config('sentfold-lite.max_body_kb', 1024) * 1024;
        if ($body === null || $max <= 0 || strlen($body) <= $max) {
            return $body;
        }

        return mb_strcut($body, 0, $max, 'UTF-8')."\n\n[Sentfold Lite: body cut at ".($max / 1024).' KB]';
    }

    /**
     * Attachment details only (name, type, size); the files are not stored in the Lite edition.
     *
     * @return list<array{name: string, type: string, size: int, inline: bool, path: null}>
     */
    private function attachments(Email $email, string $uuid, bool $mayStore): array
    {
        $list = [];
        foreach ($email->getAttachments() as $i => $part) {
            /** @var DataPart $part */
            $list[] = [
                'name' => $part->getFilename() ?? ('attachment-'.($i + 1)),
                'type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'size' => strlen($part->getBody()),
                'inline' => $part->getDisposition() === 'inline',
                'path' => null,
            ];
        }

        return $list;
    }
}
