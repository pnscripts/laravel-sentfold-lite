<?php

namespace PNScripts\SentfoldLite\Console;

use Illuminate\Console\Command;
use PNScripts\SentfoldLite\Models\ArchivedMail;

class PruneCommand extends Command
{
    protected $signature = 'sentfold-lite:prune
        {--days= : Keep this many days (default: sentfold.retention_days)}
        {--dry-run : Count what would be deleted without deleting}';

    protected $description = 'Delete archived mail older than the retention period';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('sentfold-lite.retention_days', 90);
        if (! is_numeric($days) || (int) $days < 1) {
            $this->error('The retention period must be a whole number of days, 1 or more.');

            return self::FAILURE;
        }
        $days = (int) $days;
        $query = ArchivedMail::query()->olderThanDays($days);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} archived messages are older than {$days} days. Nothing was deleted (dry run).");

            return self::SUCCESS;
        }

        $deleted = 0;
        $query->select(['id'])->chunkById(500, function ($chunk) use (&$deleted) {
            $deleted += ArchivedMail::query()->whereKey($chunk->modelKeys())->delete();
        });

        $this->info("Deleted {$deleted} archived messages older than {$days} days.");

        return self::SUCCESS;
    }
}
