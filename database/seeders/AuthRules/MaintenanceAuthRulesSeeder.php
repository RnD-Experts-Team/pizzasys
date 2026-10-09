<?php

namespace Database\Seeders\AuthRules;

/**
 * MaintenancePizza. The MOS operates everything; store managers (`reports view`
 * through their store role) raise tickets and read their own stores.
 */
class MaintenanceAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Maintenance';
    }

    protected function rules(): array
    {
        return [
            // ── Tickets: lists and analytics ─────────────────────────────────
            ['GET', '/tickets', 'scoped', ['mos', 'reports view'], 'allows_empty' => true],
            ['POST', '/tickets', 'none', ['reports view', 'mos']],
            ['GET', '/tickets/analytics', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['GET', '/tickets/*/issues', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['GET', '/maintenance-analytics/*', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['GET', '/stores/*/tickets', 'scoped', ['reports view', 'mos']],
            ['POST', '/stores/*/tickets', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/tickets/analytics', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/issues/*/history', 'scoped', ['reports view', 'mos']],
            // "This fixed it": troubleshooting solved it, no ticket -- logged
            // by whoever would have raised the ticket.
            ['POST', '/stores/*/troubleshooting-fixes', 'scoped', ['reports view', 'mos']],

            // ── One ticket ───────────────────────────────────────────────────
            ['DELETE', '/stores/*/tickets/*', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/restore', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/cancel', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/final-note', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attachments', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/technicians', 'scoped', ['mos']],

            // Notes. `notes` leaves locked notes out; `notes/all` includes them and is MOS only.
            ['GET', '/stores/*/tickets/*/notes', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/tickets/*/notes/all', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/notes', 'scoped', ['mos']],
            ['PATCH', '/stores/*/tickets/*/notes/*/privacy', 'scoped', ['mos']],

            // Store-level notes and files
            ['POST', '/stores/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/attachments', 'scoped', ['mos']],

            // ── Ticket issues ────────────────────────────────────────────────
            ['GET', '/stores/*/tickets/*/issues', 'scoped', ['reports view', 'mos']],
            ['GET', '/stores/*/tickets/*/issues/*', 'scoped', ['reports view', 'mos']],
            ['PATCH', '/stores/*/tickets/*/issues/*', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/status', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/defer', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/cancel', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/wait', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/assigned-priority', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/attachments', 'scoped', ['mos']],

            // ── Ticket records: create, mistaken, notes, attachments ─────────
            ['POST', '/stores/*/tickets/*/assignments', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/delays', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/change-technicians', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/delays/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/attachments', 'scoped', ['mos']],

            ['POST', '/stores/*/tickets/*/diagnoses', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/diagnoses/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/diagnoses/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/diagnoses/*/attachments', 'scoped', ['mos']],

            ['POST', '/stores/*/tickets/*/attendance-entries', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/attachments', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/events', 'scoped', ['mos']],
            ['PATCH', '/stores/*/tickets/*/attendance-entries/*/events/*', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/events/*/mistaken', 'scoped', ['mos']],

            ['POST', '/stores/*/tickets/*/part-usages', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/part-usages/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/part-usages/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/part-usages/*/attachments', 'scoped', ['mos']],

            ['POST', '/stores/*/tickets/*/pay-entries', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/pay-entries/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/pay-entries/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/pay-entries/*/attachments', 'scoped', ['mos']],

            ['POST', '/stores/*/tickets/*/warranties', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/warranties/*/mistaken', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/warranties/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/warranties/*/attachments', 'scoped', ['mos']],

            // ── Issue catalog and troubleshooting guides ─────────────────────
            ['GET', '/issues', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['POST', '/issues', 'scoped', ['mos'], 'allows_empty' => true],
            ['PATCH', '/issues/*', 'none', ['mos']],
            ['DELETE', '/issues/*', 'none', ['mos']],
            ['POST', '/issues/*/restore', 'none', ['mos']],
            ['POST', '/issues/*/notes', 'none', ['mos']],
            ['POST', '/issues/*/attachments', 'none', ['mos']],
            // Guides are read by store staff before they raise a ticket.
            // Store managers (`reports view`) and MOS workers at their stores; the
            // dashboard sends the store as X-Store-Id. The MOS head reads without one.
            ['GET', '/troubleshooting-guides', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['GET', '/issues/*/troubleshooting', 'scoped', ['reports view', 'mos'], 'allows_empty' => true],
            ['POST', '/issues/*/troubleshooting-guides', 'none', ['mos']],
            ['PUT', '/troubleshooting-guides/*', 'none', ['mos']],
            ['DELETE', '/troubleshooting-guides/*', 'none', ['mos']],
            ['POST', '/troubleshooting-guides/*/attachments', 'none', ['mos']],
            ['DELETE', '/troubleshooting-guides/*/attachments/*', 'none', ['mos']],
            ['POST', '/troubleshooting-steps/*/attachments', 'none', ['mos']],
            ['DELETE', '/troubleshooting-steps/*/attachments/*', 'none', ['mos']],

            // ── Technicians ──────────────────────────────────────────────────
            ['GET', '/technicians', 'none', ['mos']],
            ['GET', '/technicians/*', 'none', ['mos']],
            ['GET', '/technicians/*/analytics', 'none', ['mos']],
            ['GET', '/technician-analytics', 'none', ['mos']],
            ['POST', '/technicians', 'none', ['mos']],
            ['PATCH', '/technicians/*', 'none', ['mos']],
            ['DELETE', '/technicians/*', 'none', ['mos']],
            ['POST', '/technicians/*/restore', 'none', ['mos']],
            ['POST', '/technicians/*/notes', 'none', ['mos']],
            ['POST', '/technicians/*/attachments', 'none', ['mos']],
            ['GET', '/technician-abilities', 'none', ['mos']],
            ['PUT', '/technicians/*/abilities/*', 'none', ['mos']],
            ['DELETE', '/technicians/*/abilities/*', 'none', ['mos']],
            ['PATCH', '/technicians/*/rating', 'none', ['mos']],

            // ── Categories ───────────────────────────────────────────────────
            ['GET', '/categories', 'none', ['mos']],
            ['POST', '/categories', 'none', ['mos']],
            ['PATCH', '/categories/*', 'none', ['mos']],
            ['DELETE', '/categories/*', 'none', ['mos']],
            ['POST', '/categories/*/notes', 'none', ['mos']],
            ['POST', '/categories/*/attachments', 'none', ['mos']],

            // ── Parts and stock ──────────────────────────────────────────────
            ['GET', '/parts', 'none', ['mos']],
            ['POST', '/parts', 'none', ['mos']],
            ['PATCH', '/parts/*', 'none', ['mos']],
            ['DELETE', '/parts/*', 'none', ['mos']],
            ['POST', '/parts/*/restore', 'none', ['mos']],
            ['POST', '/parts/*/notes', 'none', ['mos']],
            ['POST', '/parts/*/attachments', 'none', ['mos']],
            ['GET', '/parts/*/price-history', 'none', ['mos']],

            ['GET', '/storage-locations', 'none', ['mos']],
            ['POST', '/storage-locations', 'none', ['mos']],
            ['DELETE', '/storage-locations/*', 'none', ['mos']],
            ['POST', '/storage-locations/*/restore', 'none', ['mos']],
            ['POST', '/storage-locations/*/notes', 'none', ['mos']],
            ['POST', '/storage-locations/*/attachments', 'none', ['mos']],
            ['GET', '/storage-locations/*/place-levels', 'none', ['mos']],
            ['POST', '/storage-locations/*/place-levels', 'none', ['mos']],
            ['PATCH', '/storage-locations/*/place-levels/*', 'none', ['mos']],
            ['DELETE', '/storage-locations/*/place-levels/*', 'none', ['mos']],
            ['POST', '/storage-locations/*/place-levels/*/values', 'none', ['mos']],
            ['PATCH', '/storage-locations/*/place-levels/*/values/*', 'none', ['mos']],
            ['DELETE', '/storage-locations/*/place-levels/*/values/*', 'none', ['mos']],

            ['GET', '/stock-movements', 'none', ['mos']],
            ['POST', '/stock-movements', 'none', ['mos']],
            ['GET', '/stock-movements/*', 'none', ['mos']],
            ['POST', '/stock-movements/*/mistaken', 'none', ['mos']],
            ['POST', '/stock-movements/*/notes', 'none', ['mos']],
            ['POST', '/stock-movements/*/attachments', 'none', ['mos']],
            ['GET', '/stock-balances', 'none', ['mos']],
            ['PUT', '/stock-balances/*/place', 'none', ['mos']],

            // ── Attendance and pay ───────────────────────────────────────────
            ['POST', '/attendance-entries', 'none', ['mos']],
            ['POST', '/attendance-entries/*/events', 'none', ['mos']],
            ['PATCH', '/attendance-entries/*/events/*', 'none', ['mos']],
            ['POST', '/attendance-entries/*/events/*/mistaken', 'none', ['mos']],
            ['GET', '/daily-pay-entries', 'none', ['mos']],
            ['POST', '/daily-pay-entries', 'none', ['mos']],
            ['GET', '/daily-pay-entries/*', 'none', ['mos']],
            ['POST', '/daily-pay-entries/*/edit', 'none', ['mos']],
            ['POST', '/daily-pay-entries/*/recalculate', 'none', ['mos']],
            ['GET', '/unpaid-work', 'none', ['mos']],

            // ── Legacy maintenance API (attend.pnepizza.com) ─────────────────
            // No route in MaintenancePizza; the dashboard's old /maintenance page still checks these.
            ['GET', '/stores/*/maintenance-requests', 'scoped', ['reports view']],
            ['GET', '/maintenance-requests/*', 'scoped', ['reports view'], 'allows_empty' => true],
        ];
    }

    protected function retired(): array
    {
        // An issue has several troubleshooting guides since 2026-10-08; guides
        // are written at /issues/*/troubleshooting-guides and /troubleshooting-guides/*.
        return [
            ['PUT', '/issues/*/troubleshooting'],
            ['DELETE', '/issues/*/troubleshooting'],
        ];
    }
}
