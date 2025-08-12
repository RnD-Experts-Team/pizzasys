<?php

namespace App\Console\Commands;

use App\Models\AuthRule;
use Illuminate\Console\Command;

class AuthzRuleList extends Command
{
    protected $signature = 'authz:rule:list {service? : Optional service name}';
    protected $description = 'List authorization rules.';

    public function handle(): int
    {
        $q = AuthRule::query()->orderBy('service')->orderByDesc('priority')->orderBy('id');
        if ($svc = $this->argument('service')) {
            $q->where('service', $svc);
        }
        $rules = $q->get();

        if ($rules->isEmpty()) {
            $this->info('No rules found.');
            return self::SUCCESS;
        }

        $this->table(
            ['id','service','method','target','roles_any','perms_any','perms_all','priority','active'],
            $rules->map(function ($r) {
                $target = $r->route_name ? "route:{$r->route_name}" : ($r->path_dsl ?? '-');
                return [
                    $r->id,
                    $r->service,
                    $r->method,
                    $target,
                    json_encode($r->roles_any ?? []),
                    json_encode($r->permissions_any ?? []),
                    json_encode($r->permissions_all ?? []),
                    $r->priority,
                    $r->is_active ? 'yes' : 'no',
                ];
            })
        );

        return self::SUCCESS;
    }
}
