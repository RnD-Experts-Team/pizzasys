<?php

namespace App\Console\Commands;

use App\Models\AuthRule;
use Illuminate\Console\Command;

class AuthzRuleDelete extends Command
{
    protected $signature = 'authz:rule:delete {id}';
    protected $description = 'Delete an authorization rule by ID.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $rule = AuthRule::find($id);
        if (!$rule) {
            $this->error("Rule #{$id} not found.");
            return self::FAILURE;
        }
        $rule->delete();
        $this->info("Rule #{$id} deleted.");
        return self::SUCCESS;
    }
}
