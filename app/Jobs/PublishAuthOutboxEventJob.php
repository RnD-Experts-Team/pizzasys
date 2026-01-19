<?php

namespace App\Jobs;

use App\Models\AuthOutboxEvent;
use App\Services\Nats\JetStreamPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishAuthOutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public string $outboxEventId) {}

    public function handle(JetStreamPublisher $publisher): void
    {
        $event = AuthOutboxEvent::query()->where('id', $this->outboxEventId)->firstOrFail();

        if ($event->published_at) {
            return; // idempotent job
        }

        $event->attempts = $event->attempts + 1;
        $event->save();

        $publisher->publish($event->subject, $event->payload);

        $event->published_at = now();
        $event->last_error = null;
        $event->save();
    }

    public function failed(\Throwable $e): void
    {
        AuthOutboxEvent::query()
            ->where('id', $this->outboxEventId)
            ->update(['last_error' => $e->getMessage()]);
    }
}
