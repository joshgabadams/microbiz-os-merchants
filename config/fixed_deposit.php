<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixed Deposit Backdating Policy
    |--------------------------------------------------------------------------
    |
    | max_backdate_days: how far into the past start_date may be set at
    | booking time. Confirmed policy value: 30 days.
    |
    | min_holding_period_hours: a backdated FD cannot be liquidated until
    | this many REAL hours have passed since the record was actually
    | created (created_at) -- not since its backdated start_date. This
    | closes the fraud vector where someone backdates a booking by the
    | full max_backdate_days and immediately liquidates it to collect
    | interest for a period the deposit never actually existed for real.
    |
    | The exact duration (24 hours) was not specified when this policy was
    | confirmed -- only that SOME minimum holding period was wanted. This
    | is a reasonable default, not a business-confirmed number; adjust
    | here if a different value is decided.
    |
    */

    'max_backdate_days' => 30,

    'min_holding_period_hours' => 24,

];
