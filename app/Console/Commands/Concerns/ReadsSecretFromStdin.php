<?php

namespace App\Console\Commands\Concerns;

/**
 * Secrets arrive on stdin, never as arguments, so they stay out of the
 * process list and shell history. The deploy control plane pipes them in.
 */
trait ReadsSecretFromStdin
{
    protected function secretFromStdin(): string
    {
        // Tests bind the value instead of a real stream.
        $raw = app()->bound('console.stdin')
            ? (string) app('console.stdin')
            : (string) stream_get_contents(STDIN);

        // printf '%s' sends no newline, echo does; accept both.
        return rtrim($raw, "\r\n");
    }
}
