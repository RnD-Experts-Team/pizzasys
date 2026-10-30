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
            // Across stores. The store is read ONLY from the parameter the
            // endpoint filters on (stores[], or store_id for one ticket), so a
            // store named any other way cannot authorise an unfiltered read.
            // Naming no store at all -- every store's tickets -- is for the MOS
            // head (MOS held as a global role) and super admins only; anyone
            // else, even holding `mos` or `reports view` globally, names their
            // stores and gets those.
            ['GET', '/tickets', 'scoped', ['mos', 'reports view'], 'sources' => ['query' => ['stores']], 'roles' => ['MOS']],
            ['POST', '/tickets', 'none', ['reports view', 'mos']],
            ['GET', '/tickets/analytics', 'scoped', ['reports view', 'mos'], 'sources' => ['query' => ['stores']], 'roles' => ['MOS']],
            ['GET', '/tickets/*/issues', 'scoped', ['reports view', 'mos'], 'sources' => ['query' => ['store_id']], 'roles' => ['MOS']],
            // Money and technician coverage are the MOS head's view, not a store's:
            // matched before the store-readable wildcard below.
            ['GET', '/maintenance-analytics/finances', 'none', ['mos'], 'priority' => 0],
            ['GET', '/maintenance-analytics/coverage', 'none', ['mos'], 'priority' => 0],
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
            ['POST', '/stores/*/tickets/*/issues/*/cancel', 'scoped', ['mos']],

            // The v2 workflow (docs/MAINTENANCE-V2.md in MaintenancePizza). Every
            // step is the MOS's at their stores, except the two the STORE takes:
            // answering a "we need more information" request and confirming the
            // repair works. Approval has its own path so it can later be limited
            // to real approver roles without touching the rest.
            ['POST', '/stores/*/tickets/*/issues/*/review', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/info-requests', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/info-requests/*/answer', 'scoped', ['reports view', 'mos']],
            ['POST', '/stores/*/tickets/*/issues/*/inspections', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/inspections/*/result', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/approval-request', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/approvals', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/schedule', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/schedules/*/move', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/work-done', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/confirmation', 'scoped', ['reports view', 'mos']],
            ['POST', '/stores/*/tickets/*/issues/*/close', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/jump', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/assigned-priority', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/issues/*/attachments', 'scoped', ['mos']],
            // Bookings and warranties are made by the workflow steps now, but
            // notes and files can still be added to them afterwards.
            ['POST', '/stores/*/tickets/*/assignments/*/notes', 'scoped', ['mos']],
            ['POST', '/stores/*/tickets/*/assignments/*/attachments', 'scoped', ['mos']],
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

            // ── Categories ───────────────────────────────────────────────────
            ['GET', '/categories', 'none', ['mos']],
            ['POST', '/categories', 'none', ['mos']],
            ['PATCH', '/categories/*', 'none', ['mos']],
            ['DELETE', '/categories/*', 'none', ['mos']],
            ['POST', '/categories/*/notes', 'none', ['mos']],
            ['POST', '/categories/*/attachments', 'none', ['mos']],

            // ── Parts and stock ──────────────────────────────────────────────
            ['GET', '/parts', 'none', ['mos']],
            ['GET', '/parts/*', 'none', ['mos']],
            ['POST', '/parts', 'none', ['mos']],
            ['PATCH', '/parts/*', 'none', ['mos']],
            ['DELETE', '/parts/*', 'none', ['mos']],
            ['POST', '/parts/*/restore', 'none', ['mos']],
            ['POST', '/parts/*/notes', 'none', ['mos']],
            ['POST', '/parts/*/attachments', 'none', ['mos']],
            ['GET', '/parts/*/price-history', 'none', ['mos']],
            // Serial-numbered units of a part, and correcting one.
            ['GET', '/parts/*/units', 'none', ['mos']],
            ['PATCH', '/part-units/*', 'none', ['mos']],

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
            // Stock counts: a sheet of expected vs counted, applied as one correction.
            ['GET', '/stock-counts', 'none', ['mos']],
            ['POST', '/stock-counts', 'none', ['mos']],
            ['GET', '/stock-counts/*', 'none', ['mos']],
            ['PATCH', '/stock-counts/*', 'none', ['mos']],
            ['POST', '/stock-counts/*/apply', 'none', ['mos']],
            ['DELETE', '/stock-counts/*', 'none', ['mos']],

            // ── Suppliers ────────────────────────────────────────────────────
            // Where parts are bought (Walmart, Amazon...). Paid and named, never booked.
            ['GET', '/suppliers', 'none', ['mos']],
            ['POST', '/suppliers', 'none', ['mos']],
            ['GET', '/suppliers/*', 'none', ['mos']],
            ['PATCH', '/suppliers/*', 'none', ['mos']],
            ['DELETE', '/suppliers/*', 'none', ['mos']],
            ['POST', '/suppliers/*/restore', 'none', ['mos']],
            ['POST', '/suppliers/*/notes', 'none', ['mos']],
            ['POST', '/suppliers/*/attachments', 'none', ['mos']],

            // ── Trips (technician clocking) ──────────────────────────────────
            // The specialist's side. The technician's side is the signed trip
            // link (/public/trips/{token}/...), which never reaches the auth
            // middleware and so has no rule here.
            ['GET', '/trips', 'none', ['mos']],
            ['POST', '/trips', 'none', ['mos']],
            ['GET', '/trips/*', 'none', ['mos']],
            ['PATCH', '/trips/*', 'none', ['mos']],
            ['POST', '/trips/*/link', 'none', ['mos']],
            ['POST', '/trips/*/close', 'none', ['mos']],
            ['POST', '/trips/*/reopen', 'none', ['mos']],
            ['POST', '/trips/*/mistaken', 'none', ['mos']],
            ['POST', '/trips/*/stops', 'none', ['mos']],
            ['POST', '/trips/*/stops/*/mistaken', 'none', ['mos']],
            ['POST', '/trips/*/sessions', 'none', ['mos']],
            ['PATCH', '/trips/*/sessions/*', 'none', ['mos']],
            ['POST', '/trips/*/sessions/*/mistaken', 'none', ['mos']],
            ['POST', '/trips/*/sessions/*/events', 'none', ['mos']],
            ['PATCH', '/trips/*/sessions/*/events/*', 'none', ['mos']],
            ['POST', '/trips/*/sessions/*/events/*/mistaken', 'none', ['mos']],
            ['POST', '/trips/*/notes', 'none', ['mos']],
            ['POST', '/trips/*/attachments', 'none', ['mos']],
            ['GET', '/trip-sessions/unpaid', 'none', ['mos']],

            // ── Payments ─────────────────────────────────────────────────────
            // One payment = one invoice we paid, to one technician or supplier.
            ['GET', '/payments', 'none', ['mos']],
            ['POST', '/payments', 'none', ['mos']],
            // Pickers: the issues a payment line or a stock movement is for, and
            // the inspections an Inspection line can pay.
            ['GET', '/ticket-issues', 'none', ['mos']],
            ['GET', '/issue-inspections', 'none', ['mos']],
            ['GET', '/payments/*', 'none', ['mos']],
            ['POST', '/payments/*/edit', 'none', ['mos']],
            ['POST', '/payments/*/mistaken', 'none', ['mos']],
            ['POST', '/payments/*/notes', 'none', ['mos']],
            ['POST', '/payments/*/attachments', 'none', ['mos']],
            ['GET', '/unpaid-work', 'none', ['mos']],
            // Parts a technician bought with his own money, waiting to be paid back.
            ['GET', '/technician-purchases', 'none', ['mos']],
            ['POST', '/technician-purchases', 'none', ['mos']],
            ['POST', '/technician-purchases/*/mistaken', 'none', ['mos']],

            // ── Settings ─────────────────────────────────────────────────────
            // Approval thresholds and how long an idle trip link lives.
            ['GET', '/maintenance-settings', 'none', ['mos']],
            ['PUT', '/maintenance-settings', 'none', ['mos']],

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

            // Maintenance v2 (2026-11): per-ticket records, hand-logged attendance,
            // daily pay sheets and the free status setter are gone. Bookings,
            // inspections, clocked trips and payments replace them.
            ['POST', '/stores/*/tickets/*/assignments'],
            ['POST', '/stores/*/tickets/*/assignments/*/delays'],
            ['POST', '/stores/*/tickets/*/assignments/*/change-technicians'],
            ['POST', '/stores/*/tickets/*/assignments/*/mistaken'],
            ['POST', '/stores/*/tickets/*/assignments/*/delays/*/mistaken'],
            ['POST', '/stores/*/tickets/*/diagnoses'],
            ['POST', '/stores/*/tickets/*/diagnoses/*/mistaken'],
            ['POST', '/stores/*/tickets/*/diagnoses/*/notes'],
            ['POST', '/stores/*/tickets/*/diagnoses/*/attachments'],
            ['POST', '/stores/*/tickets/*/attendance-entries'],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/mistaken'],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/notes'],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/attachments'],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/events'],
            ['PATCH', '/stores/*/tickets/*/attendance-entries/*/events/*'],
            ['POST', '/stores/*/tickets/*/attendance-entries/*/events/*/mistaken'],
            ['POST', '/stores/*/tickets/*/part-usages'],
            ['POST', '/stores/*/tickets/*/part-usages/*/mistaken'],
            ['POST', '/stores/*/tickets/*/part-usages/*/notes'],
            ['POST', '/stores/*/tickets/*/part-usages/*/attachments'],
            ['POST', '/stores/*/tickets/*/pay-entries'],
            ['POST', '/stores/*/tickets/*/pay-entries/*/mistaken'],
            ['POST', '/stores/*/tickets/*/pay-entries/*/notes'],
            ['POST', '/stores/*/tickets/*/pay-entries/*/attachments'],
            ['POST', '/stores/*/tickets/*/warranties'],
            ['POST', '/stores/*/tickets/*/warranties/*/mistaken'],
            ['POST', '/attendance-entries'],
            ['POST', '/attendance-entries/*/events'],
            ['PATCH', '/attendance-entries/*/events/*'],
            ['POST', '/attendance-entries/*/events/*/mistaken'],
            ['GET', '/daily-pay-entries'],
            ['POST', '/daily-pay-entries'],
            ['GET', '/daily-pay-entries/*'],
            ['POST', '/daily-pay-entries/*/edit'],
            ['POST', '/daily-pay-entries/*/recalculate'],
            ['POST', '/stores/*/tickets/*/technicians'],
            ['POST', '/stores/*/tickets/*/issues/status'],
            ['POST', '/stores/*/tickets/*/issues/*/defer'],
            ['POST', '/stores/*/tickets/*/issues/*/wait'],
            ['PATCH', '/technicians/*/rating'],
        ];
    }
}
