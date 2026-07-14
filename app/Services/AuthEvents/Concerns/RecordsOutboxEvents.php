<?php

namespace App\Services\AuthEvents\Concerns;

use App\Jobs\PublishOutboxEventJob;
use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use Illuminate\Http\Request;

trait RecordsOutboxEvents
{
    private function recordEvent(string $subject, array $data, ?Request $request = null, array $metaOverrides = []): void
    {
        $factory = app(AuthEventFactory::class);
        $outbox = app(AuthOutboxService::class);

        $envelope = $factory->make($subject, $data, $request, $metaOverrides);
        $row = $outbox->record($subject, $envelope);

        PublishOutboxEventJob::dispatch($row->id);
    }
}
