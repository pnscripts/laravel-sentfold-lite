<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('sentfold-lite.connection');
    }

    public function up(): void
    {
        Schema::create(config('sentfold-lite.table', 'sentfold_lite_messages'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mailer', 64)->nullable();
            $table->string('mailable')->nullable()->index();
            $table->string('notification')->nullable()->index();
            $table->string('message_id')->nullable();
            $table->string('subject', 998)->nullable();
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();
            $table->json('attachments')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('redactions')->default(0);
            $table->boolean('envelope_only')->default(false);
            $table->uuid('resent_from')->nullable()->index();
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('sentfold-lite.table', 'sentfold_lite_messages'));
    }
};
