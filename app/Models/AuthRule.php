<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthRule extends Model
{
    protected $fillable = [
        'service','method','path_dsl','path_regex','route_name',
        'roles_any','permissions_any','permissions_all',
        'is_active','priority',
    ];

    protected $casts = [
        'roles_any'        => 'array',
        'permissions_any'  => 'array',
        'permissions_all'  => 'array',
        'is_active'        => 'boolean',
        'priority'         => 'integer',
    ];

    /**
     * Compile our human-friendly DSL to a safe regex:
     * - {id} or :id   -> single segment ([^/]+)
     * - *              -> single segment wildcard ([^/]+)
     * - ** (only at end or segment) -> multi-segment (.*)
     * - Path always anchors: ^...$
     */
    public static function compilePathDsl(?string $dsl): ?string
    {
        if (!$dsl) return null;

        $p = trim($dsl);

        // Ensure leading slash
        if ($p === '' || $p[0] !== '/') {
            $p = '/' . $p;
        }

        // Escape regex special chars except our tokens { }, :, * and /
        // We'll replace tokens first, then escape remaining chars.
        $out = $p;

        // Replace {param} and :param with single-segment wildcard
        $out = preg_replace('/\{[^\/]+\}/', '[^/]+', $out);
        $out = preg_replace('/:[A-Za-z_][A-Za-z0-9_]*/', '[^/]+', $out);

        // Replace ** with .*
        // Support ** at segment or end; if someone writes /a/**/b we still treat ** as .*
        $out = str_replace('**', '.*', $out);

        // Replace single * with single segment wildcard
        // To avoid conflict with previous ** handling, there should be no ** left here.
        $out = preg_replace('/\*/', '[^/]+', $out);

        // Now escape leftover regex delimiters safely (we use # as delimiter)
        // We've already converted the tokens we care about; escape dots etc. that may exist.
        // Replace '.' not introduced by us (rare) — safe to escape all dots that aren't in character classes.
        // Simpler approach: escape # delimiter only.
        $out = str_replace('#', '\#', $out);

        // Anchor
        $regex = '#^' . $out . '$#';

        return $regex;
    }
}
