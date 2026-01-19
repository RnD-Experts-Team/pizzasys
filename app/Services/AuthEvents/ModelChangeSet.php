<?php

namespace App\Services\AuthEvents;

class ModelChangeSet
{
    /**
     * Build a changed_fields array from two arrays (old/new) for a specific allowlist.
     * Returns:
     * [
     *   'field' => ['from' => <old>, 'to' => <new>],
     * ]
     */
    public static function fromArrays(array $old, array $new, array $allowFields): array
    {
        $changes = [];

        foreach ($allowFields as $field) {
            $from = $old[$field] ?? null;
            $to   = $new[$field] ?? null;

            if ($from !== $to) {
                $changes[$field] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $changes;
    }

    /**
     * If you still want the "fields($model, ...)" style, keep it too.
     * (Optional)
     */
    public static function fields($model, array $allowFields): array
    {
        $changes = [];

        foreach ($allowFields as $field) {
            if ($model->wasChanged($field)) {
                $changes[$field] = [
                    'from' => $model->getOriginal($field),
                    'to' => $model->getAttribute($field),
                ];
            }
        }

        return $changes;
    }
}
