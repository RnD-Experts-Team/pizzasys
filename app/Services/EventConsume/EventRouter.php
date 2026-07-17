<?php

namespace App\Services\EventConsume;

use Exception;

class EventRouter
{
    private array $map;

    public function __construct()
    {
        $devMode = (bool) config('nats.dev_mode');

        $hiringPrefix = $devMode
            ? 'hiring.testing.v1'
            : 'hiring.v1';

        $this->map = [
            // HIRING → replicate employees into auth database
            "{$hiringPrefix}.employee.created" => \App\Services\EventConsume\Handlers\EmployeeCreatedHandler::class,
            "{$hiringPrefix}.employee.updated" => \App\Services\EventConsume\Handlers\EmployeeUpdatedHandler::class,
        ];
    }

    public function resolve(string $subject): string
    {
        if (!isset($this->map[$subject])) {
            throw new Exception("No handler for subject '{$subject}'");
        }

        return $this->map[$subject];
    }
}
