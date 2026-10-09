<?php

namespace Database\Seeders\AuthRules;

/**
 * OperationsPizza: scheduling and the time clock, run by a store manager for
 * one store. Every route carries the store CODE as {storeId}; the resolver
 * maps it to stores.id. Reads expose hourly rates and labor cost.
 *
 *   view schedule   — read the week, roster, availability, time off, templates
 *   manage schedule — everything that writes, including publishing a week,
 *                     clock punches (payroll, via TCP) and Humanity sync
 */
class OperationsAuthRulesSeeder extends AuthRuleSeeder
{
    private const READ = ['view schedule', 'manage schedule'];
    private const WRITE = ['manage schedule'];

    protected function service(): string
    {
        return 'Operations';
    }

    protected function rules(): array
    {
        return [
            // Auth-chain smoke test.
            ['GET', '/v1/health', 'none'],

            // ── Week grid ────────────────────────────────────────────────────
            ['GET', '/v1/stores/*/schedule/week', 'scoped', self::READ],
            ['GET', '/v1/stores/*/schedule/employees', 'scoped', self::READ],
            ['GET', '/v1/stores/*/schedule/departments', 'scoped', self::READ],
            ['GET', '/v1/stores/*/schedule/insights', 'scoped', self::READ],

            // ── Planned shifts (written to Humanity) ─────────────────────────
            ['POST', '/v1/stores/*/shifts', 'scoped', self::WRITE],
            ['GET', '/v1/stores/*/shifts/*', 'scoped', self::READ],
            ['POST', '/v1/stores/*/shifts/*', 'scoped', self::WRITE],
            ['DELETE', '/v1/stores/*/shifts/*', 'scoped', self::WRITE],

            // ── Actual shifts (worked time, written to TCP) ──────────────────
            ['POST', '/v1/stores/*/actual-shifts', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/actual-shifts/*', 'scoped', self::WRITE],
            ['DELETE', '/v1/stores/*/actual-shifts/*', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/actual-shifts/*/absent', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/actual-shifts/*/merge', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/actual-shifts/*/split', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/shift-assignments/*/confirm-actual', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/shift-assignments/*/absent-actual', 'scoped', self::WRITE],

            // ── Availability and time off ────────────────────────────────────
            ['GET', '/v1/stores/*/availability', 'scoped', self::READ],
            ['POST', '/v1/stores/*/availability-overrides', 'scoped', self::WRITE],
            ['DELETE', '/v1/stores/*/availability-overrides/*', 'scoped', self::WRITE],
            ['GET', '/v1/stores/*/time-off', 'scoped', self::READ],
            ['POST', '/v1/stores/*/time-off', 'scoped', self::WRITE],
            ['DELETE', '/v1/stores/*/time-off/*', 'scoped', self::WRITE],

            // ── Templates ────────────────────────────────────────────────────
            ['GET', '/v1/stores/*/schedule-templates', 'scoped', self::READ],
            ['POST', '/v1/stores/*/schedule-templates', 'scoped', self::WRITE],
            ['GET', '/v1/stores/*/schedule-templates/*', 'scoped', self::READ],
            ['POST', '/v1/stores/*/schedule-templates/*', 'scoped', self::WRITE],
            ['DELETE', '/v1/stores/*/schedule-templates/*', 'scoped', self::WRITE],

            // ── Published weeks (local snapshot + screenshot) ────────────────
            ['GET', '/v1/stores/*/published-schedules', 'scoped', self::READ],
            ['POST', '/v1/stores/*/published-schedules', 'scoped', self::WRITE],
            ['GET', '/v1/stores/*/published-schedules/*', 'scoped', self::READ],
            ['DELETE', '/v1/stores/*/published-schedules/*', 'scoped', self::WRITE],

            // ── Bulk week operations ─────────────────────────────────────────
            ['POST', '/v1/stores/*/schedule/bulk/create-shifts', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/schedule/bulk/copy-week', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/schedule/bulk/apply-template', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/schedule/bulk/clear-week', 'scoped', self::WRITE],
            ['GET', '/v1/stores/*/schedule/bulk/*', 'scoped', self::READ],
            ['POST', '/v1/stores/*/schedule/bulk/*/retry-failed', 'scoped', self::WRITE],

            // ── Time clock (a manager punching for an employee) ──────────────
            ['GET', '/v1/stores/*/on-the-clock', 'scoped', self::READ],
            ['GET', '/v1/stores/*/employees/*/clock-status', 'scoped', self::READ],
            ['POST', '/v1/stores/*/employees/*/clock-in', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/employees/*/clock-out', 'scoped', self::WRITE],
            // Only registered when TCP_BREAKS_ENABLED is on.
            ['POST', '/v1/stores/*/employees/*/break-start', 'scoped', self::WRITE],
            ['POST', '/v1/stores/*/employees/*/break-end', 'scoped', self::WRITE],

            // ── Humanity sync for one employee ───────────────────────────────
            ['GET', '/v1/stores/*/employees/*/sync-status', 'scoped', self::READ],
            ['POST', '/v1/stores/*/employees/*/humanity-sync', 'scoped', self::WRITE],
        ];
    }

    protected function retired(): array
    {
        // The draft OperationsServiceSeeder's catch-alls, replaced by the rows above.
        return [
            ['GET', '/v1/stores/{storeId}/**'],
            ['POST', '/v1/stores/{storeId}/**'],
            ['DELETE', '/v1/stores/{storeId}/**'],
            ['POST', '/v1/stores/{storeId}/published-schedules'],
            ['DELETE', '/v1/stores/{storeId}/published-schedules/{publishedId}'],
        ];
    }
}
