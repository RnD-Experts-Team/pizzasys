<?php

namespace Database\Seeders;

use App\Models\ServiceClient;
use Database\Seeders\AuthRules\OperationsAuthRulesSeeder;
use Illuminate\Database\Seeder;

/**
 * Registers the OperationsPizza scheduling service with pizzasys.
 *
 *   1. the `Operations` service client (printing its raw token ONCE),
 *   2. its auth rules (OperationsAuthRulesSeeder).
 *
 * The permissions (`view schedule`, `manage schedule`) and their grant to
 * Store Manager live in RolesAndPermissionsSeeder; run that first.
 *
 * Run with:
 *   php artisan db:seed --class=OperationsServiceSeeder
 *
 * The generated token goes into OperationsPizza's AUTH_SERVER_CALL_TOKEN.
 * Only its sha256 is stored here, so it cannot be recovered later — re-run with
 * OPERATIONS_ROTATE_TOKEN=1 to issue a new one.
 */
class OperationsServiceSeeder extends Seeder
{
    /**
     * The service identity. This ONE string does three jobs and they must all
     * agree with OperationsPizza's AUTH_SERVER_SERVICE_NAME:
     *   - service_clients.name  (ServiceCallerAuthenticator looks it up)
     *   - auth_rules.service    (AuthorizationResolver filters on it)
     *   - the `service` field of the verify request
     */
    private const SERVICE = 'Operations';

    public function run(): void
    {
        $this->seedServiceClient();
        $this->call(OperationsAuthRulesSeeder::class);
    }

    private function seedServiceClient(): void
    {
        $existing = ServiceClient::where('name', self::SERVICE)->first();
        $rotate = (bool) env('OPERATIONS_ROTATE_TOKEN', false);

        if ($existing && !$rotate) {
            $this->command?->info("Service client '" . self::SERVICE . "' already exists — token left alone.");
            $this->command?->line('  Re-run with OPERATIONS_ROTATE_TOKEN=1 to issue a new token.');

            return;
        }

        // 64 hex chars. Only the hash is persisted.
        $token = bin2hex(random_bytes(32));

        ServiceClient::updateOrCreate(
            ['name' => self::SERVICE],
            [
                'token_hash' => hash('sha256', $token),
                'is_active' => true,
                'expires_at' => null,
                'notes' => 'OperationsPizza — scheduling. Seeded by OperationsServiceSeeder.',
            ]
        );

        $this->command?->newLine();
        $this->command?->warn('╭─ Service token for OperationsPizza ' . ($rotate ? '(ROTATED)' : '(NEW)'));
        $this->command?->warn('│');
        $this->command?->warn('│  AUTH_SERVER_CALL_TOKEN=' . $token);
        $this->command?->warn('│');
        $this->command?->warn('│  Shown ONCE — only its sha256 is stored. Put it in');
        $this->command?->warn('│  OperationsPizza/.env now.');
        $this->command?->warn('╰─');
        $this->command?->newLine();
    }
}
