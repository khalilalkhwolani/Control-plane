<?php

declare(strict_types=1);

return [
    'frontend_url' => env('FRONTEND_URL', 'http://127.0.0.1:5190'),
    /*
     | Temporary owner decision: finish the license/customer workflow first.
     | Set CONTROL_CENTER_REQUIRE_MFA=true before any production hosting.
     */
    'mfa_required' => filter_var(env('CONTROL_CENTER_REQUIRE_MFA', false), FILTER_VALIDATE_BOOLEAN),
    'mfa_issuer' => env('CONTROL_CENTER_MFA_ISSUER', 'Pro One Control Center'),
    'mfa_step_up_seconds' => (int) env('CONTROL_CENTER_MFA_STEP_UP_SECONDS', 300),
    'lockout_max_attempts' => (int) env('CONTROL_CENTER_LOCKOUT_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('CONTROL_CENTER_LOCKOUT_MINUTES', 15),
    'recovery_code_count' => 8,
    'password_min_length' => 12,
    'signing' => [
        'key_id' => env('CONTROL_CENTER_LICENSE_KEY_ID'),
        'private_key_path' => env('CONTROL_CENTER_LICENSE_PRIVATE_KEY_PATH'),
    ],
];
