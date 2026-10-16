<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Archive outgoing mail
    |--------------------------------------------------------------------------
    |
    | Every message Laravel sends (Mailables, notifications, Mail::raw, ...)
    | is stored after it was handed to the transport. Turn it off per
    | environment with SENTFOLD_LITE_ENABLED=false.
    |
    */

    'enabled' => env('SENTFOLD_LITE_ENABLED', true),

    // Database connection and table. null uses the default connection.
    'connection' => env('SENTFOLD_LITE_DB_CONNECTION'),
    'table' => 'sentfold_lite_messages',

    /*
    |--------------------------------------------------------------------------
    | What is stored
    |--------------------------------------------------------------------------
    */

    'store_html' => true,
    'store_text' => true,

    // Bodies longer than this (in kilobytes) are cut, with a note at the end.
    'max_body_kb' => 1024,

    // Attachment names, types and sizes are stored; the files are not (the full Sentfold can keep them).

    // Mailable or notification classes that are never archived.
    'except' => [
        // \App\Mail\SecretReport::class,
    ],

    // Classes archived without bodies or attachment files: only the
    // envelope (subject, addresses, class, tags, time). Laravel's password
    // reset notification is a good candidate.
    'envelope_only' => [
        \Illuminate\Auth\Notifications\ResetPassword::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Regular expressions applied to the subject and bodies before they are
    | stored. Each pattern => replacement pair runs through preg_replace().
    | Keep a capture group for the label so the archive stays readable.
    |
    */

    'redact' => [
        // "password: hunter2", "Парола = 1234", "Password: <b>hunter2</b>"
        '/(?<![\p{L}\d])(password|passwd|pwd|passcode|парола)(\s*[:=]\s*(?:<[^>]+>\s*)*)([^\s<"\'&]+)/iu' => '$1$2[redacted]',
        // Tokens and signatures in links: ?token=..., &signature=...
        '/([?&](?:token|signature|code|key|secret|access_token|api_key|auth)=)[^&\s"\'<>]+/i' => '$1[redacted]',
        // Laravel's password reset links: /reset-password/<token>
        '#(/reset-password/)[A-Za-z0-9]+#' => '$1[redacted]',
        // Bearer tokens
        '/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/' => '$1[redacted]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | "php artisan sentfold-lite:prune" deletes archived mail older than this many
    | days, with its stored attachment files. Schedule it daily.
    |
    */

    'retention_days' => env('SENTFOLD_LITE_RETENTION_DAYS', 90),


];
