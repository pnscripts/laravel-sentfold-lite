<?php

namespace PNScripts\SentfoldLite;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PNScripts\SentfoldLite\Console\PruneCommand;
use PNScripts\SentfoldLite\Listeners\ArchiveSentMessage;

/** Sentfold Lite by PN Scripts (MIT): archives every e-mail the app sends. */
class SentfoldLiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sentfold-lite.php', 'sentfold-lite');
        $this->app->singleton(Archiver::class);
    }

    public function boot(): void
    {
        Event::listen(MessageSent::class, ArchiveSentMessage::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneCommand::class]);
            $this->publishes([__DIR__.'/../config/sentfold-lite.php' => config_path('sentfold-lite.php')], 'sentfold-lite-config');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'sentfold-lite-migrations');
        }
    }
}
