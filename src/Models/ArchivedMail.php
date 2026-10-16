<?php

namespace PNScripts\SentfoldLite\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PNScripts\SentfoldLite\Database\Factories\ArchivedMailFactory;

/**
 * One archived outgoing message.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $mailer
 * @property string|null $mailable
 * @property string|null $notification
 * @property string|null $message_id
 * @property string|null $subject
 * @property list<array{address: string, name: string}>|null $from
 * @property list<array{address: string, name: string}>|null $to
 * @property list<array{address: string, name: string}>|null $cc
 * @property list<array{address: string, name: string}>|null $bcc
 * @property list<array{address: string, name: string}>|null $reply_to
 * @property string|null $html
 * @property string|null $text
 * @property list<array{name: string, type: string, size: int, inline: bool, path: null}>|null $attachments
 * @property list<string>|null $tags
 * @property array<string, string>|null $metadata
 * @property int $redactions
 * @property bool $envelope_only
 * @property string|null $resent_from
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon|null $delivery_updated_at
 */
class ArchivedMail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'reply_to' => 'array',
            'attachments' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'redactions' => 'integer',
            'envelope_only' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ArchivedMailFactory
    {
        return ArchivedMailFactory::new();
    }

    public function getTable(): string
    {
        return config('sentfold-lite.table', 'sentfold_lite_messages');
    }

    public function getConnectionName(): ?string
    {
        return config('sentfold-lite.connection') ?? parent::getConnectionName();
    }

    /**
     * "Ana Petrova <ana@example.com>, office@example.com"
     *
     * @param  list<array{address: string, name: string}>|null  $addresses
     */
    public static function formatAddresses(?array $addresses): string
    {
        return collect($addresses ?? [])
            ->map(fn (array $a) => ($a['name'] ?? '') !== '' ? "{$a['name']} <{$a['address']}>" : $a['address'])
            ->implode(', ');
    }

    /**
     * @return list<string>
     */
    public function recipientAddresses(): array
    {
        return collect([$this->to, $this->cc, $this->bcc])
            ->flatten(1)
            ->filter()
            ->pluck('address')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The mailable or notification class without its namespace.
     */
    public function sourceName(): ?string
    {
        $class = $this->mailable ?? $this->notification;

        return $class !== null ? class_basename($class) : null;
    }

    public function scopeOlderThanDays(Builder $query, int $days): Builder
    {
        return $query->where('sent_at', '<', now()->subDays($days));
    }

    public function scopeSentTo(Builder $query, string $address): Builder
    {
        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], strtolower($address)).'%';

        return $query->where(function (Builder $q) use ($needle) {
            foreach (['to', 'cc', 'bcc'] as $column) {
                $q->orWhereRaw('lower('.$q->getQuery()->getGrammar()->wrap($column).') like ?', [$needle]);
            }
        });
    }
}
