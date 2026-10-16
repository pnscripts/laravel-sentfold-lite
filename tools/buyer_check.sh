#!/usr/bin/env bash
# Buyer check: a fresh Laravel app requires the package from the repository archive (what Packagist
# serves: git archive with export-ignore), migrates, sends a mail with a password and an attachment,
# and checks the archive row, the redaction and the prune command. Copyright (c) 2026 PN Scripts. MIT.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
B="$ROOT/build/buyer"
rm -rf "$B" && mkdir -p "$B/pkg"
git -C "$ROOT" archive HEAD | tar -x -C "$B/pkg"
COMPOSER_PROCESS_TIMEOUT=900 composer create-project laravel/laravel "$B/app" --no-interaction -q
cd "$B/app"
composer config repositories.sentfold-lite '{"type": "path", "url": "../pkg", "options": {"symlink": false}}'
composer require pnscripts/laravel-sentfold-lite:@dev --no-interaction -q
php artisan migrate --no-interaction -q
php artisan tinker --execute='
Illuminate\Support\Facades\Mail::raw("Your password: hunter2 and link https://shop.test/reset?token=abc123", function ($m) {
    $m->to("ana@example.test", "Ana")->subject("Welcome")->attachData("%PDF-1.4", "invoice.pdf", ["mime" => "application/pdf"]);
});
$r = PNScripts\SentfoldLite\Models\ArchivedMail::first();
echo json_encode(["rows" => PNScripts\SentfoldLite\Models\ArchivedMail::count(), "subject" => $r->subject, "to" => $r->to[0]["address"], "text" => $r->text, "redactions" => $r->redactions, "attachment" => $r->attachments[0]["name"] . " " . $r->attachments[0]["size"] . " path=" . var_export($r->attachments[0]["path"], true)]), "\n";
'
php artisan sentfold-lite:prune --dry-run
echo "Laravel $(php artisan --version | grep -o '[0-9][0-9.]*'), PHP $(php -r 'echo PHP_VERSION;'), installed $(composer show pnscripts/laravel-sentfold-lite | grep -i '^versions' )"
