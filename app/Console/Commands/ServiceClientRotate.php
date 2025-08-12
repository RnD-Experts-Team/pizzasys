<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesExpiry;
use App\Models\ServiceClient;
use Illuminate\Console\Command;

class ServiceClientRotate extends Command
{
    use ParsesExpiry;

    protected $signature = 'service-client:rotate
                            {name : Existing service name}
                            {--date= : New expiry date (MM-DD-YYYY or YYYY-MM-DD); end of that day}
                            {--never : Never expires (overrides --date)}
                            {--preview : Show token but do not save changes}';

    protected $description = 'Rotate the token for an existing service (prints new plaintext once).';

    public function handle(): int
    {
        $name = $this->argument('name');
        $client = ServiceClient::where('name', $name)->first();

        if (!$client) {
            $this->error("Service '{$name}' not found.");
            return self::FAILURE;
        }

        $plain = base64_encode(random_bytes(48));
        $hash  = hash('sha256', $plain);

        $never = (bool) $this->option('never');
        $date  = $this->option('date');

        $expiresAt = $client->expires_at; // keep existing unless changed
        if ($never) {
            $expiresAt = null;
        } elseif ($date) {
            $parsed = $this->parseDateOnly($date);
            if (!$parsed) {
                $this->error("Invalid --date format. Use MM-DD-YYYY or YYYY-MM-DD.");
                return self::FAILURE;
            }
            $expiresAt = $parsed;
        }

        $this->warn("NEW TOKEN (save it now):");
        $this->line($plain);

        if ($this->option('preview')) {
            $this->info("Preview only. No changes saved.");
            return self::SUCCESS;
        }

        $client->token_hash = $hash;
        $client->expires_at = $expiresAt;
        $client->save();

        if ($expiresAt) {
            $this->info("New token expires at end of: " . $expiresAt->toDateString());
        } else {
            $this->info("New token set to never expire.");
        }

        return self::SUCCESS;
    }
}
