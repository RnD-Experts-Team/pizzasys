<?php

namespace Database\Seeders;

use App\Models\ServiceClient;
use Database\Seeders\AuthRules\ToolboxAuthRulesSeeder;
use Illuminate\Database\Seeder;

/**
 * Registers the ToolboxPizza service (breaks, tickets, workbooks) with pizzasys.
 *
 *   1. the `Toolbox` service client (printing its raw token ONCE),
 *   2. its auth rules (ToolboxAuthRulesSeeder).
 *
 * The permissions (`view tickets`, `administer tickets`, `create workbooks`)
 * and their grants live in RolesAndPermissionsSeeder; run that first.
 *
 * Run with:
 *   php artisan db:seed --class=ToolboxServiceSeeder
 *
 * The generated token goes into ToolboxPizza's AUTH_SERVER_CALL_TOKEN.
 * Only its sha256 is stored here, so it cannot be recovered later — re-run with
 * TOOLBOX_ROTATE_TOKEN=1 to issue a new one.
 */
class ToolboxServiceSeeder extends Seeder
{
    /** Must equal ToolboxPizza's AUTH_SERVER_SERVICE_NAME (client name, rule service, verify field). */
    private const SERVICE = 'Toolbox';

    public function run(): void
    {
        $this->seedServiceClient();
        $this->call(ToolboxAuthRulesSeeder::class);
    }

    private function seedServiceClient(): void
    {
        $existing = ServiceClient::where('name', self::SERVICE)->first();
        $rotate = (bool) env('TOOLBOX_ROTATE_TOKEN', false);

        if ($existing && !$rotate) {
            $this->command?->info("Service client '" . self::SERVICE . "' already exists — token left alone.");
            $this->command?->line('  Re-run with TOOLBOX_ROTATE_TOKEN=1 to issue a new token.');

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
                'notes' => 'ToolboxPizza — breaks, tickets, workbooks. Seeded by ToolboxServiceSeeder.',
            ]
        );

        $this->command?->newLine();
        $this->command?->warn('╭─ Service token for ToolboxPizza ' . ($rotate ? '(ROTATED)' : '(NEW)'));
        $this->command?->warn('│');
        $this->command?->warn('│  AUTH_SERVER_CALL_TOKEN=' . $token);
        $this->command?->warn('│');
        $this->command?->warn('│  Shown ONCE — only its sha256 is stored. Put it in');
        $this->command?->warn('│  ToolboxPizza/.env now.');
        $this->command?->warn('╰─');
        $this->command?->newLine();
    }
}
