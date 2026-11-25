<?php

namespace Database\Seeders\AuthRules;

/**
 * InventoryPizza: units, items, count links, entries and dough & sauce counts.
 * Store-less reads take the store from the X-Store-Id header the dashboard sends.
 */
class InventoryAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Inventory';
    }

    protected function rules(): array
    {
        return [
            // ── Units (global catalog) ───────────────────────────────────────
            // Unified calendar feed (stores=a,b in the query, each one checked). Store
            // staff see their own stores; the inventory specialists, holding their
            // role globally, every store — and only this layer.
            ['GET', '/inventory/calendar/feed', 'scoped', ['reports view', 'inventory handling', 'dough and sauce'], 'sources' => ['query' => ['stores']], 'roles' => ['Inventory Handler', 'Dough and Sauce']],
            ['GET', '/inventory/units', 'none', ['inventory handling']],
            ['GET', '/inventory/units/*', 'none', ['inventory handling']],
            ['POST', '/inventory/units', 'none', ['inventory handling']],
            ['PUT', '/inventory/units/*', 'none', ['inventory handling']],
            ['DELETE', '/inventory/units/*', 'none', ['inventory handling']],

            // ── Items ────────────────────────────────────────────────────────
            ['GET', '/inventory/items', 'scoped', ['inventory handling'], 'allows_empty' => true, 'roles' => ['Department Viewer']],
            ['GET', '/inventory/items/*', 'scoped', ['inventory handling'], 'allows_empty' => true, 'roles' => ['Department Viewer']],
            ['POST', '/inventory/items', 'none', ['inventory handling']],
            ['PUT', '/inventory/items/*', 'none', ['inventory handling']],
            ['PATCH', '/inventory/items/*/active', 'none', ['inventory handling']],
            ['DELETE', '/inventory/items/*', 'none', ['inventory handling']],

            // ── Count links and entries ──────────────────────────────────────
            ['POST', '/inventory/links', 'scoped', ['inventory handling']],
            ['GET', '/inventory/links/*', 'scoped', ['inventory handling'], 'allows_empty' => true, 'roles' => ['Department Viewer']],
            ['GET', '/inventory/stores/*/links', 'scoped', ['inventory handling'], 'roles' => ['Department Viewer']],
            ['GET', '/inventory/stores/*/entries', 'scoped', ['inventory handling'], 'roles' => ['Department Viewer']],
            ['GET', '/inventory/entries/*', 'scoped', ['inventory handling'], 'allows_empty' => true, 'roles' => ['Department Viewer']],
            ['GET', '/inventory/entries/*/history', 'none', ['inventory handling'], 'roles' => ['Department Viewer']],
            ['PATCH', '/inventory/entry-items/*', 'scoped', ['inventory handling'], 'allows_empty' => true],

            // ── Dough & sauce counts (b-dashboard-pizza/docs/DOUGH-SAUCE-ACCESS.md)
            // Only `dough and sauce` (workers per store, head by role), as on QA and Data.
            ['GET', '/inventory/stores/*/counts', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce', 'Department Viewer']],
            // Every active store in one call: the specialist's weekly grid. The doc
            // suggests all_stores, but that mode also demands a store-role row at
            // every store, which a global specialist does not have. A global check
            // gives the intended result: the head passes, workers get 403 and use
            // the per-store route above for their own stores.
            ['GET', '/inventory/counts', 'none', ['dough and sauce'], 'roles' => ['Department Viewer']],

            // ── Report builder datasets (same contract in every service) ─────
            // The catalog is code, identical for everyone; each dataset query takes
            // the permission of the existing route that shows the same data. The
            // body's `stores` are store codes; an empty list is the global check.
            // Entries are the counts' headers: GET /inventory/stores/*/entries.
            // Counts are the Dough & Sauce counts: the same permission and role as
            // GET /inventory/stores/*/counts.
            ['GET', '/report-datasets', 'none'],
            ['POST', '/report-datasets/inventory.entries/query', 'scoped', ['inventory handling'], 'allows_empty' => true, 'sources' => ['body' => ['stores']], 'roles' => ['Department Viewer']],
            ['POST', '/report-datasets/inventory.counts/query', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce', 'Department Viewer'], 'allows_empty' => true, 'sources' => ['body' => ['stores']]],
        ];
    }
}
