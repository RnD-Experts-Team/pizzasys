<?php

namespace Database\Seeders\AuthRules;

/**
 * HiringPizza: employees, hiring/separation requests, manager dashboard
 * reports, employee metrics and shirt milestones (Employee Obsession).
 */
class HiringAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Hiring';
    }

    protected function rules(): array
    {
        return [
            // ── Employees ────────────────────────────────────────────────────
            ['GET', '/v1/employees', 'scoped', ['employee obsession', 'hiring specialist', 'hiring admin', 'reports view', 'cleaning specialist'], 'allows_empty' => true],
            ['GET', '/v1/stores/*/employees', 'scoped', ['hiring specialist', 'hiring admin', 'reports view', 'employee obsession']],
            ['GET', '/v1/stores/*/employees/*', 'scoped', ['reports view', 'hiring admin', 'hiring specialist', 'employee obsession']],
            ['GET', '/v1/stores/*/employees/*/operational', 'scoped', ['reports view', 'hiring admin', 'hiring specialist', 'employee obsession']],
            ['POST', '/v1/stores/*/employees', 'scoped', ['hiring admin', 'hiring specialist']],
            ['POST', '/v1/stores/*/employees/*', 'scoped', ['hiring admin', 'hiring specialist']],
            ['PATCH', '/v1/stores/*/employees/*/status', 'scoped', ['hiring admin', 'hiring specialist']],
            // Reference lists the employee forms read; PUT edits them.
            ['GET', '/v1/reference-catalog', 'none'],
            ['PUT', '/v1/reference-catalog', 'none', ['hiring admin']],

            // ── Hiring and separation requests ───────────────────────────────
            ['GET', '/v1/requests', 'scoped', ['hiring specialist', 'hiring admin', 'reports view', 'employee obsession'], 'allows_empty' => true],
            ['GET', '/v1/stores/*/requests', 'scoped', ['hiring specialist', 'hiring admin', 'reports view', 'employee obsession']],
            ['POST', '/v1/stores/*/hiring-requests', 'scoped', ['reports view']],
            ['POST', '/v1/stores/*/hiring-requests/*/decision', 'scoped', ['hiring admin', 'hiring specialist']],
            ['POST', '/v1/stores/*/separation-requests', 'scoped', ['reports view']],
            ['POST', '/v1/stores/*/separation-requests/*/decision', 'scoped', ['hiring admin', 'hiring specialist']],

            // ── Manager dashboard and labor reports ──────────────────────────
            ['GET', '/v1/stores/*/manager-dashboard/*', 'scoped', ['reports view']],
            ['GET', '/v1/stores/*/labor/*', 'scoped', ['reports view']],
            ['GET', '/v1/reports', 'scoped', ['reports view'], 'allows_empty' => true],
            ['GET', '/v1/reports/*/*', 'scoped', ['reports view']],
            ['GET', '/average-hourly-pay/*/*', 'scoped', ['reports view']],
            ['GET', '/high-hours-employees/*/*', 'scoped', ['reports view']],

            // ── Employee metrics import ──────────────────────────────────────
            ['GET', '/v1/employee-metrics', 'none', ['import data']],
            ['POST', '/v1/employee-metrics/import', 'none', ['import data']],

            // ── Shirts: store queue (store managers per store, EO everywhere) ─
            ['GET', '/v1/store-shirt-milestones', 'scoped', ['reports view', 'employee obsession'], 'allows_empty' => true],
            ['GET', '/v1/stores/*/shirt-milestones', 'scoped', ['reports view', 'employee obsession']],
            ['POST', '/v1/stores/*/shirt-milestones', 'scoped', ['reports view', 'employee obsession']],
            ['GET', '/v1/stores/*/shirt-milestones/*', 'scoped', ['reports view', 'employee obsession']],
            ['POST', '/v1/stores/*/shirt-milestones/*/entry', 'scoped', ['reports view', 'employee obsession']],
            ['GET', '/v1/stores/*/employees/*/shirts', 'scoped', ['reports view', 'employee obsession']],

            // ── Shirts: fulfilment. No store in the path, so Employee Obsession
            // passes on its global role; a store in the request checks that store.
            ['GET', '/v1/shirt-milestones', 'scoped', ['reports view', 'employee obsession'], 'allows_empty' => true],
            ['GET', '/v1/shirt-milestones/*', 'scoped', ['reports view', 'employee obsession'], 'allows_empty' => true],
            ['POST', '/v1/shirt-milestones/*/order', 'scoped', ['employee obsession'], 'allows_empty' => true],
            ['PATCH', '/v1/shirt-milestones/*/delivery-date', 'scoped', ['employee obsession'], 'allows_empty' => true],
            ['POST', '/v1/shirt-milestones/*/deliver', 'scoped', ['reports view', 'employee obsession'], 'allows_empty' => true],
            ['POST', '/v1/shirt-milestones/*/cancel', 'scoped', ['reports view', 'employee obsession'], 'allows_empty' => true],

            // ── Shirts: catalog ──────────────────────────────────────────────
            ['GET', '/v1/shirt-catalog', 'none'],
            ['POST', '/v1/shirt-colors', 'none', ['employee obsession']],
            ['PUT', '/v1/shirt-colors/*', 'none', ['employee obsession']],
            ['DELETE', '/v1/shirt-colors/*', 'none', ['employee obsession']],
            ['POST', '/v1/shirt-logos', 'none', ['employee obsession']],
            ['POST', '/v1/shirt-logos/*', 'none', ['employee obsession']],
            ['DELETE', '/v1/shirt-logos/*', 'none', ['employee obsession']],
            ['POST', '/v1/shirt-templates', 'none', ['employee obsession']],
            ['POST', '/v1/shirt-templates/*', 'none', ['employee obsession']],
            ['DELETE', '/v1/shirt-templates/*', 'none', ['employee obsession']],
        ];
    }

    protected function retired(): array
    {
        // Milestone gifts were removed from HiringPizza (commit 02d00ec "new EO").
        return [
            ['POST', '/v1/stores/*/milestone-gift-requests'],
            ['POST', '/v1/stores/*/milestone-gift-requests/*/final-status'],
            ['POST', '/v1/stores/*/milestone-gift-requests/*/gift-decision'],
            ['GET', '/v1/stores/*/milestone-gift-requests/*/rating'],
            ['POST', '/v1/milestone-gift-questions'],
            ['POST', '/v1/milestone-gift-questions/**'],
            ['PUT', '/v1/milestone-gift-questions/**'],
            ['DELETE', '/v1/milestone-gift-questions/**'],
        ];
    }
}
