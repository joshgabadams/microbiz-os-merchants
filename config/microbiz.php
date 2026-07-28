<?php

// config/microbiz.php
//
// Configuration for the platform services merged in from microbiz-os-core:
// RBAC enforcement, audit logging, and notification dispatch.

return [

    'audit' => [
        'redact_fields' => ['password', 'password_confirmation', 'token', 'otp', 'secret'],
    ],

    'notifications' => [
        'sms_provider' => env('SMS_PROVIDER', 'stub'),
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'stub'),
        'push_provider' => env('PUSH_PROVIDER', 'stub'),
    ],

];
