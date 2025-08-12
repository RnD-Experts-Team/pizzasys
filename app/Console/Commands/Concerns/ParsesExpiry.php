<?php

namespace App\Console\Commands\Concerns;

use Carbon\Carbon;

trait ParsesExpiry
{
    /**
     * Parse a date-only string into a Carbon endOfDay timestamp.
     * Accepts MM-DD-YYYY or YYYY-MM-DD.
     * Returns Carbon|null
     */
    protected function parseDateOnly(?string $date): ?Carbon
    {
        if (!$date) return null;

        // Try MM-DD-YYYY
        try {
            $c = Carbon::createFromFormat('m-d-Y', $date)->endOfDay();
            return $c;
        } catch (\Throwable $e) {}

        // Try YYYY-MM-DD
        try {
            $c = Carbon::createFromFormat('Y-m-d', $date)->endOfDay();
            return $c;
        } catch (\Throwable $e) {}

        // Try Carbon's smart parse (still endOfDay)
        try {
            $c = Carbon::parse($date)->endOfDay();
            return $c;
        } catch (\Throwable $e) {}

        return null; // caller will handle invalid format
    }
}
