<?php

namespace App\Console\Commands;

use App\Models\AuthRule;
use Illuminate\Console\Command;

class AuthzRuleAdd extends Command
{
    protected $signature = 'authz:rule:add
                            {service : e.g., data}
                            {method : GET|POST|PUT|PATCH|DELETE|ANY or comma list}
                            {target : Path DSL (e.g., /orders/{id}, /orders/**) OR route:route.name}
                            {--roles= : comma list, e.g., admin,manager}
                            {--perms-any= : comma list, e.g., orders.view,metrics.view}
                            {--perms-all= : comma list, e.g., orders.update,orders.delete}
                            {--priority=100 : integer priority (higher first)}
                            {--inactive : create as inactive}';

    protected $description = 'Add an authorization rule using a friendly Path DSL or route name.';

    public function handle(): int
    {
        $service = $this->argument('service');
        $methodArg = strtoupper($this->argument('method'));
        $target = $this->argument('target');

        $methods = array_map('trim', explode(',', $methodArg));
        $roles = $this->csv($this->option('roles'));
        $permsAny = $this->csv($this->option('perms-any'));
        $permsAll = $this->csv($this->option('perms-all'));
        $priority = (int) $this->option('priority');
        $inactive = (bool) $this->option('inactive');

        $isRoute = str_starts_with($target, 'route:');
        $routeName = $isRoute ? substr($target, 6) : null;
        $pathDsl = $isRoute ? null : $target;

        $pathRegex = $pathDsl ? AuthRule::compilePathDsl($pathDsl) : null;

        if (!$routeName && !$pathRegex) {
            $this->error('You must provide either a route:NAME or a valid path DSL.');
            return self::FAILURE;
        }

        foreach ($methods as $m) {
            $m = strtoupper($m);
            if ($m === '') continue;

            $rule = AuthRule::create([
                'service'          => $service,
                'method'           => $m,
                'route_name'       => $routeName,
                'path_dsl'         => $pathDsl,
                'path_regex'       => $pathRegex,
                'roles_any'        => !empty($roles) ? array_values($roles) : null,
                'permissions_any'  => !empty($permsAny) ? array_values($permsAny) : null,
                'permissions_all'  => !empty($permsAll) ? array_values($permsAll) : null,
                'priority'         => $priority,
                'is_active'        => !$inactive,
            ]);

            $this->info("Rule #{$rule->id} added: [{$service}] {$m} " .
                ($routeName ? "route:{$routeName}" : $pathDsl) .
                ' | roles_any=' . json_encode($roles) .
                ' | perms_any=' . json_encode($permsAny) .
                ' | perms_all=' . json_encode($permsAll) .
                " | priority={$priority}" .
                ' | active=' . ($rule->is_active ? 'yes' : 'no'));
        }

        $this->line("\nPath DSL tips:");
        $this->line("  /orders             exact path");
        $this->line("  /orders/{id}        one segment wildcard");
        $this->line("  /orders/*           one segment wildcard");
        $this->line("  /orders/**          any subpath (multi segments)");
        $this->line("  /orders/{id}/items/*  mix of variables + wildcard");
        $this->line("Or use route:order.show to match route name.");

        return self::SUCCESS;
    }

    private function csv(?string $s): array
    {
        if (!$s) return [];
        return array_values(array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== ''));
    }
}
