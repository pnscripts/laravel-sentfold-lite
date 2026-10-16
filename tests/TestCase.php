<?php

namespace PNScripts\SentfoldLite\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use PNScripts\SentfoldLite\SentfoldLiteServiceProvider;
use PNScripts\SentfoldLite\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            SentfoldLiteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.paths', array_merge($app['config']->get('view.paths', []), [__DIR__.'/views']));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Messages the "array" mailer has sent in this test.
     *
     * @return \Illuminate\Support\Collection<int, \Symfony\Component\Mailer\SentMessage>
     */
    protected function sentMessages()
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }
}
