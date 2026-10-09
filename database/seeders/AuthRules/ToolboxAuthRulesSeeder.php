<?php

namespace Database\Seeders\AuthRules;

/**
 * ToolboxPizza: breaks, tickets and workbooks. Toolbox does its own per-item
 * authorization, so most routes only need a signed-in user here:
 *
 *  - breaks act on the caller and nobody else;
 *  - a ticket's reporter, routed assignees and participants decide who may see
 *    or act on it (TicketAccessService), and those people may not hold the
 *    ticket's store, so per-ticket routes stay open;
 *  - workbook visibility tags decide who sees and edits a folder, workbook or row.
 *
 * pizzasys gates what Toolbox cannot:
 *   view tickets       (per store) — the store's whole ticket queue
 *   administer tickets (global)    — the section / level / assignment catalog
 *   create workbooks   (per store) — creating items owned by a store
 *
 * Toolbox refuses employee tokens outright.
 */
class ToolboxAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Toolbox';
    }

    protected function rules(): array
    {
        return [
            // ── Breaks: self-service ─────────────────────────────────────────
            ['GET', '/v1/break-types', 'none'],
            ['GET', '/v1/break-settings', 'none'],
            ['POST', '/v1/break-settings', 'none'],
            ['GET', '/v1/break-milestones', 'none'],
            ['POST', '/v1/break-milestones', 'none'],
            ['DELETE', '/v1/break-milestones/*', 'none'],
            ['GET', '/v1/breaks', 'none'],
            ['POST', '/v1/breaks', 'none'],
            ['GET', '/v1/breaks/active', 'none'],
            ['POST', '/v1/breaks/start', 'none'],
            ['GET', '/v1/breaks/day', 'none'],
            ['GET', '/v1/breaks/day/export', 'none'],
            ['GET', '/v1/breaks/*', 'none'],
            ['POST', '/v1/breaks/*', 'none'],
            ['DELETE', '/v1/breaks/*', 'none'],
            ['POST', '/v1/breaks/*/stop', 'none'],
            ['POST', '/v1/breaks/*/notes', 'none'],

            // ── Ticket catalog (HQ). The section list is every user's picker. ─
            ['GET', '/v1/ticket-sections', 'none'],
            ['POST', '/v1/ticket-sections', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-sections/*', 'none', ['administer tickets']],
            ['DELETE', '/v1/ticket-sections/*', 'none', ['administer tickets']],
            ['GET', '/v1/ticket-levels', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-levels', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-levels/*', 'none', ['administer tickets']],
            ['DELETE', '/v1/ticket-levels/*', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-levels/*/sections', 'none', ['administer tickets']],
            ['GET', '/v1/ticket-assignments', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-assignments', 'none', ['administer tickets']],
            ['POST', '/v1/ticket-assignments/*', 'none', ['administer tickets']],
            ['DELETE', '/v1/ticket-assignments/*', 'none', ['administer tickets']],

            // ── Tickets ──────────────────────────────────────────────────────
            // Inbox: filtered locally to tickets the caller reported, takes part
            // in, or is routed.
            ['GET', '/v1/tickets', 'none'],
            // The store queue is NOT filtered locally.
            ['GET', '/v1/stores/*/tickets', 'scoped', ['view tickets']],
            // "Report a problem" is on every page.
            ['POST', '/v1/stores/*/tickets', 'none'],
            // One ticket: Toolbox's reporter/assignee/participant matrix decides.
            ['GET', '/v1/stores/*/tickets/*', 'none'],
            ['POST', '/v1/stores/*/tickets/*', 'none'],
            ['POST', '/v1/stores/*/tickets/*/status', 'none'],
            ['POST', '/v1/stores/*/tickets/*/reopen', 'none'],
            ['POST', '/v1/stores/*/tickets/*/responses', 'none'],
            ['POST', '/v1/stores/*/tickets/*/notes', 'none'],
            ['POST', '/v1/stores/*/tickets/*/attachments', 'none'],
            ['GET', '/v1/stores/*/tickets/*/participants', 'none'],
            ['POST', '/v1/stores/*/tickets/*/participants', 'none'],
            ['DELETE', '/v1/stores/*/tickets/*/participants/*', 'none'],
            ['GET', '/v1/stores/*/tickets/*/recipients', 'none'],

            // ── Workbooks: creating items owned by a store ───────────────────
            ['POST', '/v1/stores/*/workbook-folders', 'scoped', ['create workbooks']],
            ['POST', '/v1/stores/*/workbook-folders/*/workbooks', 'scoped', ['create workbooks']],
            ['POST', '/v1/stores/*/workbooks/*/rows', 'scoped', ['create workbooks']],

            // ── Workbooks: everything else (visibility tags decide) ──────────
            ['GET', '/v1/workbook-options', 'none'],
            ['GET', '/v1/workbook-folders', 'none'],
            ['GET', '/v1/workbook-folders/*', 'none'],
            ['POST', '/v1/workbook-folders/*', 'none'],
            ['DELETE', '/v1/workbook-folders/*', 'none'],
            ['POST', '/v1/workbook-folders/*/visibility', 'none'],
            ['GET', '/v1/workbook-folders/*/workbooks', 'none'],
            ['GET', '/v1/workbooks/*', 'none'],
            ['POST', '/v1/workbooks/*', 'none'],
            ['DELETE', '/v1/workbooks/*', 'none'],
            ['POST', '/v1/workbooks/*/visibility', 'none'],
            ['GET', '/v1/workbooks/*/columns', 'none'],
            ['POST', '/v1/workbooks/*/columns', 'none'],
            ['GET', '/v1/workbooks/*/rows', 'none'],
            ['POST', '/v1/workbooks/*/rows/reorder', 'none'],
            ['GET', '/v1/workbooks/*/rows/*', 'none'],
            ['POST', '/v1/workbooks/*/rows/*', 'none'],
            ['DELETE', '/v1/workbooks/*/rows/*', 'none'],
            ['POST', '/v1/workbooks/*/rows/*/visibility', 'none'],
        ];
    }
}
