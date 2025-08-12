<?php

namespace App\Console\Commands;

use App\Models\AuthRule;
use Illuminate\Console\Command;

class AuthzRuleActivate extends Command
{
    protected $signature = 'authz:rule:activate {id}';
    protected $description = 'Activate an authorization rule.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $rule = AuthRule::find($id);
        if (!$rule) {
            $this->error("Rule #{$id} not found.");
            return self::FAILURE;
        }
        $rule->is_active = true;
        $rule->save();
        $this->info("Rule #{$id} activated.");
        return self::SUCCESS;
    }
}
