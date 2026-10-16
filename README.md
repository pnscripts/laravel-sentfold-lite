# Sentfold Lite by PN Scripts

A free archive of every e-mail your Laravel application sends (MIT). Sentfold Lite listens to
Laravel's `MessageSent` event and stores the subject, all addresses, the HTML and text bodies, the
attachment names and sizes, and the Mailable or notification that sent it, so "did we send the
customer the invoice?" has an answer. Passwords, tokens and reset links are **redacted before
anything is stored**, and a prune command keeps the archive to your retention period.

## Quick start

```bash
composer require pnscripts/laravel-sentfold-lite
php artisan migrate
```

Send any mail: it appears in the `sentfold_lite_messages` table
(`PNScripts\SentfoldLite\Models\ArchivedMail`). Schedule the retention in `routes/console.php`:

```php
Schedule::command('sentfold-lite:prune')->daily();
```

```php
use PNScripts\SentfoldLite\Models\ArchivedMail;

ArchivedMail::sentTo('ana@example.com')->latest('sent_at')->get(['subject', 'sent_at', 'mailable']);
```

## What is stored

| Field | Notes |
|---|---|
| Subject, from, to, cc, bcc, reply-to | Names and addresses; `sentTo()` searches recipients |
| HTML and text bodies | After redaction; each can be switched off; cut at `max_body_kb` (1 MB by default) |
| Attachments | Name, MIME type, size, inline or not (not the files) |
| Mailable or notification class, mailer, tags, metadata, message ID, sent time | |

Works for Mailables, notifications, `Mail::raw()` and anything else that goes through Laravel's
mailer, queued or not. If archiving fails (a database outage, a bad pattern), the error is reported
and **the mail still goes out**.

## Privacy

- **Redaction before storing**: regular expressions in `config/sentfold-lite.php`. The defaults
  cover "password: …" (also inside HTML tags), `?token=`, `signature=`, `code=`, `key=`, `api_key=`,
  `/reset-password/<token>` and Bearer tokens.
- **Envelope-only classes**: Laravel's `ResetPassword` notification is archived without bodies:
  subject, recipients and time only. Add your own classes.
- **Exclusions**: classes in `except` are not archived at all.
- **Retention**: `php artisan sentfold-lite:prune [--days=N] [--dry-run]` deletes old rows in chunks
  of 500. Default 90 days (`SENTFOLD_LITE_RETENTION_DAYS`).

`php artisan vendor:publish --tag=sentfold-lite-config` copies the configuration.

## Tested

- PHPUnit with Orchestra Testbench on Laravel 13 and PHP 8.4: 37 tests.
- A fresh Laravel app with the package from the repository archive (`tools/buyer_check.sh`):
  migrate, a real mail with a password, a reset token and a PDF attachment, the stored row, the
  redaction and the prune command.
- Laravel 12 (declared in `composer.json`) and PHP 8.2/8.3 were not run in this release.

## Sentfold (full edition)

Sentfold adds a **Filament 4/5 resource** to browse, search and preview the archive, **resend** to
the original or new recipients, **.eml download**, kept attachment files and **delivery status from
Mailgun and Postmark webhooks** (delivered, bounced, marked as spam):
https://pnscripts.com/marketplace/laravel-sentfold. Lite is complete for archiving and never asks for
payment.

## Licence

MIT, see LICENSE. Copyright (c) 2026 ПН СКРИПТС ЕООД (PN Scripts).
