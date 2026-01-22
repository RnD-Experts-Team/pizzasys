<?php

return [
    // If no rule matches a request, allow or deny? (default: deny)
    'allow_if_no_rule' => true,

    // Super roles that always allow (optional)
    'super_roles' => ['super-admin'],

    'decision_cache_seconds' => 20,
];
