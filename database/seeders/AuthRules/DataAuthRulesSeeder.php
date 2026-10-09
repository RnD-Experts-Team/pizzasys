<?php

namespace Database\Seeders\AuthRules;

/**
 * LC_PIZZA_DATA: reports, the data-entry engine (keys, values, due), tags,
 * goals, CSV imports, employee debriefs and dough & sauce recipes.
 */
class DataAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Data';
    }

    protected function rules(): array
    {
        return [
            // ── Per-store reports: /reports/{name}/{store}/{date} ────────────
            ['GET', '/reports/dspr/**', 'scoped', ['reports view']],
            ['GET', '/reports/dashboard/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/customer-count-and-sales/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/portal-weekly/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/channel-sales/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/phone-and-adjusted-sales/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/cash-control/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/lto/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/promo/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/non-negotiable-reports/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/go-to/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/cleaning-review/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/portioning/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/customer-service/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/transfer-in-out/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/orders-vs-sales/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/hnr-plus/*/*', 'scoped', ['reports view']],
            ['GET', '/reports/employees/*/*', 'scoped', ['reports view']],
            // Hourly sales next to the schedule builder; schedulers read it too.
            ['GET', '/reports/scheduling-insights/*', 'scoped', ['reports view', 'view schedule', 'manage schedule']],
            // Body `stores` is a list, or "all" (no store ids → global check).
            ['POST', '/reports/multi-dashboard', 'scoped', ['reports view'], 'allows_empty' => true],

            // ── Exports ──────────────────────────────────────────────────────
            ['GET', '/export/*', 'none', ['export data']],
            ['GET', '/reports/lc-archive-zip/*', 'none', ['export data']],

            // ── Imports ──────────────────────────────────────────────────────
            ['GET', '/manual-import', 'none', ['import data']],
            ['GET', '/manual-import/**', 'none', ['import data']],
            ['POST', '/manual-import/*', 'none', ['import data']],
            ['POST', '/go-to-calls/upload-csv', 'none', ['import data']],
            ['POST', '/transfer-in-out/upload-csv', 'none', ['import data']],
            ['POST', '/inventory-orders/upload-csv', 'none', ['import data']],
            ['POST', '/hnr-plus/upload-csv', 'none', ['import data']],
            ['POST', '/cleaning-review/upload-csv', 'none', ['import data']],
            ['POST', '/customer-service/upload-csv', 'none', ['import data']],

            // ── Engine: key definitions (global) ─────────────────────────────
            ['GET', '/engine/keys', 'none', ['keys handling']],
            ['GET', '/engine/keys/**', 'none', ['keys handling']],
            ['POST', '/engine/keys', 'none', ['keys handling']],
            ['PUT', '/engine/keys/**', 'none', ['keys handling']],
            ['PATCH', '/engine/keys/*', 'none', ['keys handling']],
            ['PATCH', '/engine/keys/*/restore', 'none', ['keys handling']],
            ['DELETE', '/engine/keys/*', 'none', ['keys handling']],
            ['DELETE', '/engine/keys/*/force-delete', 'none', ['keys handling']],

            // ── Engine: per-store values and due keys ────────────────────────
            ['GET', '/engine/stores/**', 'scoped', ['data entry']],
            ['POST', '/engine/stores/**', 'scoped', ['data entry']],

            // ── Tags and goals ───────────────────────────────────────────────
            ['GET', '/tags', 'none', ['keys handling']],
            ['POST', '/tags', 'none', ['keys handling']],
            ['DELETE', '/tags/bulk', 'none', ['keys handling']],
            ['DELETE', '/tags/*', 'none', ['keys handling']],
            ['GET', '/goal-metrics', 'none', ['keys handling']],
            ['POST', '/goal-metrics', 'none', ['keys handling']],
            ['DELETE', '/goal-metrics/*', 'none', ['keys handling']],
            ['GET', '/stores/*/goals', 'scoped', ['keys handling']],
            ['POST', '/stores/*/goals', 'scoped', ['keys handling']],
            ['PUT', '/stores/*/goals/*', 'scoped', ['keys handling']],
            ['DELETE', '/stores/*/goals/*', 'scoped', ['keys handling']],

            // ── Employee debriefs (written by whoever does data entry) ───────
            ['GET', '/stores/*/employee-debriefs', 'scoped', ['reports view', 'data entry']],
            ['GET', '/stores/*/employee-debriefs/range', 'scoped', ['reports view', 'data entry']],
            ['GET', '/stores/*/employee-debriefs/types', 'scoped', ['reports view', 'data entry']],
            ['GET', '/stores/*/employee-debriefs/employee/*', 'scoped', ['reports view', 'data entry']],
            ['GET', '/stores/*/employee-debriefs/*', 'scoped', ['reports view', 'data entry']],
            ['POST', '/stores/*/employee-debriefs', 'scoped', ['data entry']],
            ['POST', '/stores/*/employee-debriefs/bulk', 'scoped', ['data entry']],
            ['DELETE', '/stores/*/employee-debriefs/*', 'scoped', ['data entry']],

            // ── Dough & sauce (b-dashboard-pizza/docs/DOUGH-SAUCE-ACCESS.md) ─
            // Only `dough and sauce`: workers hold it per store, the head through the
            // global Dough and Sauce role, which a scoped rule never sees, so the
            // role is let through by name.
            ['GET', '/stores/*/dough-sauce/daily-plan', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce']],
            ['GET', '/dough-sauce/ingredients', 'scoped', ['dough and sauce'], 'allows_empty' => true],
            ['GET', '/dough-sauce/recipes', 'none', ['dough and sauce']],
            ['POST', '/dough-sauce/recipes', 'none', ['dough and sauce']],
            ['PUT', '/dough-sauce/recipes/*', 'none', ['dough and sauce']],
            ['DELETE', '/dough-sauce/recipes/*', 'none', ['dough and sauce']],
        ];
    }
}
