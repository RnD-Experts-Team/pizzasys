<?php

namespace App\Services\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Exception;

class JetStreamPublisher
{
    private Client $client;

    public function __construct()
    {
        $config = new Configuration([
            'host' => config('nats.host'),
            'port' => config('nats.port'),
            'user' => config('nats.user'),
            'pass' => config('nats.pass'),
            'token' => config('nats.token'),
        ]);

        $this->client = new Client($config);
    }

    /**
     * Publishes to JetStream and expects a server response/ack.
     */
    public function publish(string $subject, array $payload): void
    {
        if (!config('nats.jetstream.enabled', true)) {
            throw new Exception('JetStream is disabled in config (nats.jetstream.enabled=false).');
        }

        $this->assertSubjectAllowed($subject);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new Exception('Failed to encode event payload as JSON.');
        }

        $streamName = (string) config('nats.jetstream.stream', '');
        if ($streamName === '') {
            throw new Exception('Missing JetStream stream name in config (nats.jetstream.stream).');
        }

        $stream = $this->client->getApi()->getStream($streamName);

        // JetStream publish to the stream
        $ack = $stream->put($subject, $json);

        if (!$ack) {
            throw new Exception('JetStream publish did not return a response/ack.');
        }
    }

    /**
     * Optional safety: ensure subjects being published are within configured subject filters.
     * Supports patterns like "auth.v1.>".
     */
    private function assertSubjectAllowed(string $subject): void
    {
        $patterns = (array) config('nats.jetstream.subjects', []);

        // If you don't want validation, just return when empty.
        if (count($patterns) === 0) {
            return;
        }

        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;
            if ($pattern !== '' && $this->matchesNatsSubject($subject, $pattern)) {
                return;
            }
        }

        throw new Exception(
            "Subject '{$subject}' is not allowed by config nats.jetstream.subjects."
        );
    }

    /**
     * Minimal NATS subject matcher for common wildcard usage:
     * - ">" matches any number of remaining tokens, but only at end (e.g. auth.v1.>)
     * - "*" matches exactly one token (e.g. auth.*.created)
     */
    private function matchesNatsSubject(string $subject, string $pattern): bool
    {
        $s = explode('.', $subject);
        $p = explode('.', $pattern);

        $si = 0;
        $pi = 0;

        while ($pi < count($p)) {
            $pt = $p[$pi];

            if ($pt === '>') {
                // ">" matches the rest, but should be last token
                return $pi === count($p) - 1;
            }

            if ($si >= count($s)) {
                return false;
            }

            if ($pt !== '*' && $pt !== $s[$si]) {
                return false;
            }

            $si++;
            $pi++;
        }

        // pattern fully consumed; subject must also be fully consumed
        return $si === count($s);
    }
}
