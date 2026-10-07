<?php

namespace Database\Seeders\AuthRules;

/**
 * AuditApp: camera audits, QA catalog, custom reports, cleaning chart and
 * dough & sauce plans.
 */
class QaAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'QA';
    }

    protected function rules(): array
    {
        return [
            // ── Camera audits (the dashboard sends the store as X-Store-Id) ──
            ['GET', '/camera-forms', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['POST', '/camera-forms', 'scoped', ['qa audit'], 'allows_empty' => true],
            ['GET', '/camera-forms/*', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            // The update route is POST (multipart), not PUT/PATCH.
            ['POST', '/camera-forms/*', 'scoped', ['qa audit'], 'allows_empty' => true],
            ['DELETE', '/camera-forms/*', 'scoped', ['qa audit'], 'allows_empty' => true],
            ['GET', '/camera-reports', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/camera-reports/export', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/camera-reports/exportExcel', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/camera-reports/exportImages', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/audits', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/audits/*', 'scoped', ['qa audit', 'reports view'], 'allows_empty' => true],
            ['GET', '/audits/ratings-summary/**', 'scoped', ['reports view', 'qa audit']],

            // ── QA catalog: entities and categories ──────────────────────────
            ['GET', '/entities', 'none', ['qa audit', 'qa admin']],
            ['POST', '/entities', 'none', ['qa admin']],
            ['PUT', '/entities/*', 'none', ['qa admin']],
            ['PATCH', '/entities/*', 'none', ['qa admin']],
            ['DELETE', '/entities/*', 'none', ['qa admin']],
            ['POST', '/categories', 'none', ['qa admin']],
            ['PUT', '/categories/*', 'none', ['qa admin']],
            ['PATCH', '/categories/*', 'none', ['qa admin']],
            ['DELETE', '/categories/*', 'none', ['qa admin']],

            // ── Custom reports (QA admins only) ──────────────────────────────
            ['GET', '/custom-reports', 'none', ['qa admin']],
            ['POST', '/custom-reports', 'none', ['qa admin']],
            ['GET', '/custom-reports/*', 'none', ['qa admin']],
            ['PUT', '/custom-reports/*', 'none', ['qa admin']],
            ['PATCH', '/custom-reports/*', 'none', ['qa admin']],
            ['DELETE', '/custom-reports/*', 'none', ['qa admin']],

            // ── Cleaning: store due lists (store managers, per store) ────────
            ['GET', '/cleaning/stores/*/dates/*/due', 'scoped', ['cleaning specialist']],
            ['GET', '/cleaning/stores/*/due-range', 'scoped', ['cleaning specialist']],
            ['POST', '/cleaning/stores/*/tasks/*/complete', 'scoped', ['cleaning specialist']],
            ['POST', '/cleaning/stores/*/tasks/*/uncomplete', 'scoped', ['cleaning specialist']],
            ['GET', '/cleaning/stores/*/tasks/*/history', 'scoped', ['cleaning specialist']],

            // ── Cleaning: specialist setup, evaluations and reports ──────────
            ['GET', '/cleaning/tasks', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/tasks', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/tasks/*', 'none', ['cleaning specialist']],
            ['PUT', '/cleaning/tasks/*', 'none', ['cleaning specialist']],
            ['DELETE', '/cleaning/tasks/*', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/inspection-items', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/inspection-items', 'none', ['cleaning specialist']],
            ['PUT', '/cleaning/inspection-items/*', 'none', ['cleaning specialist']],
            ['DELETE', '/cleaning/inspection-items/*', 'none', ['cleaning specialist']],
            // Store managers read their store's evaluations ("My Store" tab, X-Store-Id).
            ['GET', '/cleaning/evaluations', 'scoped', ['cleaning specialist'], 'allows_empty' => true],
            ['POST', '/cleaning/evaluations', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/evaluations/finalize', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/evaluations/reopen', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/evaluations/allocations', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/evaluations/allocations', 'none', ['cleaning specialist']],
            ['DELETE', '/cleaning/evaluations/allocations', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/evaluations/allocations/copy', 'none', ['cleaning specialist']],
            ['POST', '/cleaning/evaluations/allocations/remove', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/periods', 'scoped', ['cleaning specialist'], 'allows_empty' => true],
            ['GET', '/cleaning/settings', 'none', ['cleaning specialist']],
            ['PUT', '/cleaning/settings', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/reports/data', 'none', ['cleaning specialist']],
            ['GET', '/cleaning/reports/csv', 'none', ['cleaning specialist']],

            // ── Dough & sauce (b-dashboard-pizza/docs/DOUGH-SAUCE-ACCESS.md) ─
            // Store managers: `reports view` for their store. The specialist holds
            // `dough and sauce` through a GLOBAL role, which a scoped rule never
            // sees, so the role is let through by name.
            ['GET', '/stores/*/dough-sauce/plan', 'scoped', ['dough and sauce', 'reports view'], 'roles' => ['Dough and Sauce']],
            ['POST', '/stores/*/dough-sauce/plan', 'scoped', ['dough and sauce', 'reports view'], 'roles' => ['Dough and Sauce']],
            ['GET', '/stores/*/dough-sauce/week', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce']],
            ['PUT', '/stores/*/dough-sauce/judgement', 'scoped', ['dough and sauce'], 'roles' => ['Dough and Sauce']],
            ['GET', '/dough-sauce/plans', 'none', ['dough and sauce']],
        ];
    }

    protected function retired(): array
    {
        return [
            // The camera-form update route is POST; these never matched anything.
            ['PUT', '/camera-forms/*'],
            ['PATCH', '/camera-forms/*'],
        ];
    }
}
