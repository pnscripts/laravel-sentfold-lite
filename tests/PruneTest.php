<?php

namespace PNScripts\SentfoldLite\Tests;

use PNScripts\SentfoldLite\Models\ArchivedMail;

class PruneTest extends TestCase
{
    private function seedArchive(): void
    {
        ArchivedMail::factory()->create(['subject' => 'very old', 'sent_at' => now()->subDays(200)]);
        ArchivedMail::factory()->create(['subject' => 'old', 'sent_at' => now()->subDays(91)]);
        ArchivedMail::factory()->create(['subject' => 'recent', 'sent_at' => now()->subDays(10)]);
        ArchivedMail::factory()->create(['subject' => 'today', 'sent_at' => now()]);
    }

    public function test_prunes_older_than_the_default_90_days(): void
    {
        $this->seedArchive();
        $this->artisan('sentfold-lite:prune')->expectsOutput('Deleted 2 archived messages older than 90 days.')->assertSuccessful();
        $this->assertSame(['recent', 'today'], ArchivedMail::orderBy('sent_at')->pluck('subject')->all());
    }

    public function test_days_option(): void
    {
        $this->seedArchive();
        $this->artisan('sentfold-lite:prune', ['--days' => 5])->assertSuccessful();
        $this->assertSame(['today'], ArchivedMail::pluck('subject')->all());
    }

    public function test_retention_from_config(): void
    {
        $this->seedArchive();
        config(['sentfold-lite.retention_days' => 365]);
        $this->artisan('sentfold-lite:prune')->expectsOutput('Deleted 0 archived messages older than 365 days.')->assertSuccessful();
        $this->assertSame(4, ArchivedMail::count());
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $this->seedArchive();
        $this->artisan('sentfold-lite:prune', ['--dry-run' => true])
            ->expectsOutput('2 archived messages are older than 90 days. Nothing was deleted (dry run).')
            ->assertSuccessful();
        $this->assertSame(4, ArchivedMail::count());
    }

    public function test_invalid_days_fail(): void
    {
        $this->seedArchive();
        $this->artisan('sentfold-lite:prune', ['--days' => 0])->assertFailed();
        $this->artisan('sentfold-lite:prune', ['--days' => 'ten'])->assertFailed();
        $this->assertSame(4, ArchivedMail::count());
    }

    public function test_large_archives_are_pruned_in_chunks(): void
    {
        ArchivedMail::factory()->count(1203)->create(['sent_at' => now()->subDays(120)]);
        ArchivedMail::factory()->create(['sent_at' => now()]);

        $this->artisan('sentfold-lite:prune')->expectsOutput('Deleted 1203 archived messages older than 90 days.')->assertSuccessful();
        $this->assertSame(1, ArchivedMail::count());
    }
}
