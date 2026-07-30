<?php

return [
    // If no rule matches a request, allow or deny? (default: deny)
    'allow_if_no_rule' => true,

    // Super roles that always allow (optional)
    'super_roles' => ['super-admin'],

    'decision_cache_seconds' => 20,

    // Initial password assigned to employees replicated from hiring events.
    'employee_default_password' => env('EMPLOYEE_DEFAULT_PASSWORD', 'Password123'),
];
