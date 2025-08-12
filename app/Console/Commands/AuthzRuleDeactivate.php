<?php

namespace App\Console\Commands;

use App\Models\AuthRule;
use Illuminate\Console\Command;

class AuthzRuleDeactivate extends Command
{
    protected $signature = 'authz:rule:deactivate {id}';
    protected $description = 'Deactivate an authorization rule.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $rule = AuthRule::find($id);
        if (!$rule) {
            $this->error("Rule #{$id} not found.");
            return self::FAILURE;
        }
        $rule->is_active = false;
        $rule->save();
        $this->info("Rule #{$id} deactivated.");
        return self::SUCCESS;
    }
}
