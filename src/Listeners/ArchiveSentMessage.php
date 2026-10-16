<?php

namespace PNScripts\SentfoldLite\Listeners;

use Illuminate\Mail\Events\MessageSent;
use PNScripts\SentfoldLite\Archiver;
use Throwable;

/**
 * Archives every sent message. A failure to archive is reported but never
 * breaks the code that sent the mail.
 */
class ArchiveSentMessage
{
    public function __construct(private Archiver $archiver)
    {
    }

    public function handle(MessageSent $event): void
    {
        if (! config('sentfold-lite.enabled', true)) {
            return;
        }

        try {
            $this->archiver->archive($event->message, $event->data, $event->sent->getMessageId());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
