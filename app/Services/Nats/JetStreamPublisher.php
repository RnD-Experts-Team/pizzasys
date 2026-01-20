<?php

namespace App\Services\Nats;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Exception;
use Throwable;

class JetStreamPublisher
{
    /**
     * Build a fresh client per publish to avoid “stale connection with old creds”
     * during smoke tests where you change .env and restart containers.
     */
    private function makeClient(): Client
    {
        $host  = (string) config('nats.host');
        $port  = (int)    config('nats.port');

        if ($host === '' || $port <= 0) {
            throw new Exception('NATS host/port not configured (nats.host / nats.port).');
        }

        $token = config('nats.token');
        $user  = config('nats.user');
        $pass  = config('nats.pass');

        $opts = [
            'host' => $host,
            'port' => $port,
        ];

        // Enforce explicit auth configuration (prevents silent “no auth” mode).
        if (!empty($token)) {
            $opts['token'] = (string) $token;
        } elseif (!empty($user) || !empty($pass)) {
            // Require BOTH when using user/pass
            if (empty($user) || empty($pass)) {
                throw new Exception('NATS user/pass auth requires BOTH nats.user and nats.pass.');
            }
            $opts['user'] = (string) $user;
            $opts['pass'] = (string) $pass;
        } else {
            throw new Exception('NATS auth not configured (set nats.token OR nats.user+nats.pass).');
        }

        $config = new Configuration($opts);

        return new Client($config);
    }

    /**
     * Publishes to JetStream and validates the server ACK.
     * If auth is wrong, stream missing, or publish rejected => throws.
     *
     * @return array{stream?:string, seq?:int, duplicate?:bool} ACK data (best-effort)
     */
    public function publish(string $subject, array $payload): array
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

        $client = $this->makeClient();

        try {
            // This call must pass auth (bad creds should throw somewhere in this chain).
            $stream = $client->getApi()->getStream($streamName);

            // JetStream publish expects an ACK. If it doesn’t, we treat it as a hard failure.
            $ack = $stream->put($subject, $json);

            if ($ack === null || $ack === false) {
                throw new Exception('JetStream publish did not return an ACK.');
            }

            /**
             * Basis NATS ACK shapes can vary by version.
             * We validate by extracting common fields defensively.
             */
            $ackArr = $this->normalizeAck($ack);

            // Hard validation: must confirm stream + sequence when available.
            if (isset($ackArr['stream']) && $ackArr['stream'] !== $streamName) {
                throw new Exception(
                    "JetStream ACK stream mismatch. Expected '{$streamName}', got '{$ackArr['stream']}'."
                );
            }

            if (isset($ackArr['seq']) && (!is_int($ackArr['seq']) || $ackArr['seq'] <= 0)) {
                throw new Exception('JetStream ACK returned invalid sequence number.');
            }

            // If server explicitly indicates error in ACK payload, fail.
            if (isset($ackArr['error']) && $ackArr['error']) {
                throw new Exception('JetStream publish error: ' . (string) $ackArr['error']);
            }

            return $ackArr;
        } catch (Throwable $e) {
            /**
             * IMPORTANT:
             * This guarantees your queued job FAILS and can land in failed_jobs
             * (depending on your queue:work tries settings).
             */
            throw new Exception(
                "JetStream publish failed for subject '{$subject}' (stream '{$streamName}'): " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Turn the ACK into a simple array, no matter if it’s an object/array/json string.
     * @return array<string,mixed>
     */
    private function normalizeAck(mixed $ack): array
    {
        // If it’s already an array, use it.
        if (is_array($ack)) {
            return $ack;
        }

        // If it’s a JSON string, decode it.
        if (is_string($ack)) {
            $decoded = json_decode($ack, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return ['raw' => $ack];
        }

        // If it’s an object, try common conversions.
        if (is_object($ack)) {
            // If it has toArray()
            if (method_exists($ack, 'toArray')) {
                $arr = $ack->toArray();
                return is_array($arr) ? $arr : ['raw' => (string) $ack];
            }

            // Public properties -> array
            $arr = get_object_vars($ack);
            if (is_array($arr) && count($arr) > 0) {
                return $arr;
            }

            // Last resort: jsonSerialize()
            if ($ack instanceof \JsonSerializable) {
                $arr = $ack->jsonSerialize();
                return is_array($arr) ? $arr : ['raw' => json_encode($arr)];
            }

            // Last resort: string cast
            return ['raw' => (string) $ack];
        }

        return ['raw' => $ack];
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

        throw new Exception("Subject '{$subject}' is not allowed by config nats.jetstream.subjects.");
    }

    /**
     * Minimal NATS subject matcher:
     * - ">" matches remaining tokens, but only at end (auth.v1.>)
     * - "*" matches exactly one token (auth.*.created)
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

        return $si === count($s);
    }
}
