<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesExpiry;
use App\Models\ServiceClient;
use Illuminate\Console\Command;

class ServiceClientCreate extends Command
{
    use ParsesExpiry;

    protected $signature = 'service-client:create
                            {name : Unique service name (e.g., data)}
                            {--date= : Expiry date (MM-DD-YYYY or YYYY-MM-DD); end of that day}
                            {--never : Never expires (overrides --date)}
                            {--note= : Optional note}';

    protected $description = 'Create a service client and print the plaintext token once.';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (ServiceClient::where('name', $name)->exists()) {
            $this->error("Service '{$name}' already exists.");
            return self::FAILURE;
        }

        $plain = base64_encode(random_bytes(48));
        $hash  = hash('sha256', $plain);

        $never = (bool) $this->option('never');
        $date  = $this->option('date');

        $expiresAt = null;
        if (!$never && $date) {
            $parsed = $this->parseDateOnly($date);
            if (!$parsed) {
                $this->error("Invalid --date format. Use MM-DD-YYYY or YYYY-MM-DD.");
                return self::FAILURE;
            }
            $expiresAt = $parsed;
        }

        $note = $this->option('note');

        ServiceClient::create([
            'name'        => $name,
            'token_hash'  => $hash,
            'is_active'   => true,
            'expires_at'  => $expiresAt,   // null => never expires
            'notes'       => $note,
        ]);

        $this->info("Service '{$name}' created.");
        $this->warn('SAVE THIS TOKEN NOW (will not be shown again):');
        $this->line($plain);

        $this->line("\nCaller .env example:");
        $this->line("SERVICE_CALL_TOKEN=\"{$plain}\"");

        if ($expiresAt) {
            $this->info("Expires at end of: " . $expiresAt->toDateString());
        } else {
            $this->info("No expiry (never).");
        }

        return self::SUCCESS;
    }
}
