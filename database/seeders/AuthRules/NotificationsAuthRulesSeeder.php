<?php

namespace Database\Seeders\AuthRules;

/**
 * NotificationsPizza: announcements and the signed-in user's own notifications.
 * Announcements carry no store, so the admin routes are global checks.
 */
class NotificationsAuthRulesSeeder extends AuthRuleSeeder
{
    protected function service(): string
    {
        return 'Notifications';
    }

    protected function rules(): array
    {
        return [
            // ── Announcements: admin ─────────────────────────────────────────
            ['GET', '/announcements', 'none', ['manage announcements']],
            ['POST', '/announcements', 'none', ['manage announcements']],
            ['GET', '/announcements/*', 'none', ['manage announcements']],
            ['PUT', '/announcements/*', 'none', ['manage announcements']],
            ['DELETE', '/announcements/*', 'none', ['manage announcements']],

            // ── Announcements: the caller's own feed ─────────────────────────
            // GET /announcements/* (admin show) also matches these two paths, so
            // they sit at priority 0 to be checked first.
            ['GET', '/announcements/visible', 'none', [], 'priority' => 0],
            ['GET', '/announcements/unseen', 'none', [], 'priority' => 0],
            ['POST', '/announcements/mark-seen', 'none'],

            // ── Notifications: filtered to the caller ────────────────────────
            ['GET', '/notifications', 'none'],
            ['GET', '/notifications/unread', 'none'],
            ['POST', '/notifications/read-all', 'none'],
            ['POST', '/notifications/*/read', 'none'],
            ['GET', '/broadcasting/auth', 'none'],
            ['POST', '/broadcasting/auth', 'none'],
        ];
    }
}
