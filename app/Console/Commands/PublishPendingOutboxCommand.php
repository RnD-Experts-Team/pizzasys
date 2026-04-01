<?php

namespace App\Console\Commands;

use App\Jobs\PublishOutboxEventJob;
use App\Models\AuthOutboxEvent;
use Illuminate\Console\Command;

class PublishPendingOutboxCommand extends Command
{
    protected $signature = 'outbox:publish-pending {--chunk=100}';
    protected $description = 'Dispatch jobs for unpublished outbox events';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        AuthOutboxEvent::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->chunkById($chunkSize, function ($events) {
                foreach ($events as $event) {
                    PublishOutboxEventJob::dispatch($event->id);
                }
            });


        return self::SUCCESS;
    }
}