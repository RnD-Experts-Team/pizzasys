<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsSecretFromStdin;
use App\Models\ServiceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Register a service client with a token someone else generated.
 *
 * For the deploy control plane, which mints each service's token, puts it in
 * that service's AUTH_SERVER_CALL_TOKEN, and registers the same value here on
 * every deploy. Idempotent: running it again with the same token changes
 * nothing; with a new token it replaces the old one at once.
 *
 * Unlike service-client:create it never prints the token.
 */
class ServiceClientUpsert extends Command
{
    use ReadsSecretFromStdin;

    protected $signature = 'service-client:upsert
                            {name : Service name, as the service sends it (e.g. Hiring)}
                            {--token-stdin : Read the token from stdin}
                            {--note= : Optional note}';

    protected $description = 'Create or update a service client with a token read from stdin.';

    public function handle(): int
    {
        if (! $this->option('token-stdin')) {
            $this->error('Pass the token on stdin with --token-stdin.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');
        $token = $this->secretFromStdin();

        if (strlen($token) < 32) {
            $this->error('The token must be at least 32 characters.');

            return self::FAILURE;
        }

        $hash = hash('sha256', $token);
        $client = ServiceClient::where('name', $name)->first();

        if ($client && hash_equals((string) $client->token_hash, $hash) && $client->is_active && $client->expires_at === null) {
            $this->info("Service '{$name}' unchanged.");

            return self::SUCCESS;
        }

        $client ??= new ServiceClient(['name' => $name]);
        $created = ! $client->exists;

        $client->fill([
            'token_hash' => $hash,
            'is_active' => true,
            'expires_at' => null,
            'notes' => $this->option('note') ?? $client->notes ?? 'Managed by the deploy control plane.',
        ])->save();

        // ServiceCallerAuthenticator caches the record for a minute; a new
        // token has to work now, not in sixty seconds.
        try {
            Cache::store('redis')->forget('authz:svc_client:'.hash('sha256', $name));
        } catch (Throwable) {
            // No Redis (tests, a broken cache) only means the old record
            // lives out its last minute.
        }

        $this->info($created ? "Service '{$name}' created." : "Service '{$name}' updated.");

        return self::SUCCESS;
    }
}
