<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthRule extends Model
{
    protected $fillable = [
        'service',
        'method',
        'path_dsl',
        'path_regex',
        'route_name',
        'roles_any',
        'permissions_any',
        'permissions_all',
        'store_scope_mode',
        'store_id_sources',
        'store_match_policy',
        'store_allows_empty',
        'store_all_access_roles_any',
        'store_all_access_permissions_any',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'roles_any'                     => 'array',
        'permissions_any'               => 'array',
        'permissions_all'               => 'array',
        'store_id_sources'              => 'array',
        'store_all_access_roles_any'    => 'array',
        'store_all_access_permissions_any' => 'array',
        'store_allows_empty'            => 'boolean',
        'is_active'                     => 'boolean',
        'priority'                      => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (AuthRule $rule) {
            // Normalize
            $rule->method = strtoupper($rule->method ?: 'ANY');

            // Compile regex from DSL if DSL exists
            if (!empty($rule->path_dsl)) {
                $rule->path_regex = static::compilePathDslToRegex($rule->path_dsl);
            } else {
                $rule->path_regex = null;
            }
        });
    }

    /**
     * Compile our DSL to a safe anchored regex.
     *
     * DSL tokens:
     *  - {id} or :id -> single segment wildcard ([^/]+)
     *  - *          -> single segment wildcard ([^/]+)
     *  - **         -> multi segment wildcard (.*) (can appear as its own segment or at end)
     *
     * Ensures literals are escaped (preg_quote) so dots etc. don't become regex operators.
     */
    public static function compilePathDslToRegex(?string $dsl): ?string
    {
        if (!$dsl) return null;

        $p = trim($dsl);
        if ($p === '') return null;

        if ($p[0] !== '/') {
            $p = '/' . $p;
        }

        // Split into segments, compile each
        $segments = explode('/', ltrim($p, '/'));

        $compiled = [];
        foreach ($segments as $seg) {
            if ($seg === '') continue;

            if ($seg === '**') {
                $compiled[] = '.*';
                continue;
            }

            // Replace tokens inside segment safely.
            // Handle full-segment wildcards first:
            if ($seg === '*') {
                $compiled[] = '[^/]+';
                continue;
            }

            // If segment includes {param} or :param, treat those parts as wildcards.
            // We'll parse piecewise:
            $pattern = $seg;

            // Convert {param} to placeholder token
            $pattern = preg_replace('/\{[^\/]+\}/', '__SEG_WILD__', $pattern);
            $pattern = preg_replace('/:[A-Za-z_][A-Za-z0-9_]*/', '__SEG_WILD__', $pattern);

            // Convert any remaining single * inside segment to wildcard
            $pattern = str_replace('*', '__SEG_WILD__', $pattern);

            // Now escape the remaining literal text
            $parts = explode('__SEG_WILD__', $pattern);

            $rebuilt = '';
            $count = count($parts);
            for ($i = 0; $i < $count; $i++) {
                $rebuilt .= preg_quote($parts[$i], '#');
                if ($i !== $count - 1) {
                    $rebuilt .= '[^/]+';
                }
            }

            $compiled[] = $rebuilt;
        }

        $regexBody = '/' . implode('/', $compiled);
        return '#^' . $regexBody . '$#';
    }
}
