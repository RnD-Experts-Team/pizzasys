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

            // ── Report builder (contract v1) ─────────────────────────────────
            // The engine routes are open to any signed-in user: templates, previews
            // and runs only ever read data through the dataset routes below, which
            // carry the real permissions (one row per dataset, literal paths).
            ['GET', '/report-datasets', 'none'],
            ['GET', '/report-builder/catalog', 'none'],
            ['POST', '/report-builder/preview', 'none'],
            ['GET', '/report-builder/templates', 'none'],
            ['POST', '/report-builder/templates', 'none'],
            ['GET', '/report-builder/templates/*', 'none'],
            ['PUT', '/report-builder/templates/*', 'none'],
            ['DELETE', '/report-builder/templates/*', 'none'],
            ['POST', '/report-builder/run', 'none'],
            ['GET', '/report-builder/runs', 'none'],
            ['GET', '/report-builder/runs/*', 'none'],
            ['GET', '/report-builder/runs/*/rows', 'none'],
            ['GET', '/report-builder/runs/*/download', 'none'],
            ['GET', '/report-builder/schedules', 'none'],
            ['POST', '/report-builder/schedules', 'none'],
            ['GET', '/report-builder/schedules/*', 'none'],
            ['PUT', '/report-builder/schedules/*', 'none'],
            ['DELETE', '/report-builder/schedules/*', 'none'],
            // Datasets: body `stores` is a list of store codes; empty = global check.
            ['POST', '/report-datasets/data.daily_sales/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.employee_debriefs/query', 'scoped', ['reports view', 'data entry'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.key_values/query', 'scoped', ['reports view', 'data entry', 'keys handling'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            // Warehouse and aggregation datasets: the same data the sales, items, financial and cash reports show.
            ['POST', '/report-datasets/data.daily_items/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.hourly_sales/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.hourly_items/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.orders/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.order_lines/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.summary_items/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.summary_sales/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.summary_transactions/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.waste/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.cash/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.financials/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.transactions/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.alta_cogs/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.alta_ingredient_orders/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.alta_ingredient_usage/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.alta_waste/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            // Goals follow the /stores/*/goals rows; the app-table reports follow their /reports/... rows.
            ['POST', '/report-datasets/data.goals/query', 'scoped', ['keys handling'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.non_negotiables/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.go_to_calls/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.transfers/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.inventory_orders/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.cleaning_reviews/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.customer_service/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
            ['POST', '/report-datasets/data.hnr_plus/query', 'scoped', ['reports view'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],

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
            // Every store at once (the weekly report): the stores come from
            // ?store_ids=a,b and each one must pass, so a worker is refused
            // unless they hold the permission at all of them.
            ['GET', '/dough-sauce/daily-plans', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce'], 'sources' => ['query' => ['store_ids']]],
            ['GET', '/dough-sauce/ingredients', 'scoped', ['dough and sauce'], 'allows_empty' => true],
            ['GET', '/dough-sauce/recipes', 'none', ['dough and sauce']],
            ['POST', '/dough-sauce/recipes', 'none', ['dough and sauce']],
            ['PUT', '/dough-sauce/recipes/*', 'none', ['dough and sauce']],
            ['DELETE', '/dough-sauce/recipes/*', 'none', ['dough and sauce']],
        ];
    }

    protected function retired(): array
    {
        return [
            // Written for a store-scoped path; the route is /dough-sauce/daily-plans.
            ['GET', '/stores/*/dough-sauce/daily-plans'],
        ];
    }
}
