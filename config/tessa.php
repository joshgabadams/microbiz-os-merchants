<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TESSA Alert Detection Thresholds
    |--------------------------------------------------------------------------
    |
    | None of the numbers below are confirmed bank policy -- they are
    | reasonable, clearly-flagged defaults chosen to avoid inventing an
    | absolute currency figure (which would vary hugely by branch size).
    | Adjust here once real thresholds are confirmed with Operations/Risk.
    |
    */

    'teller_variance' => [
        // Severity is scaled by variance as a PERCENTAGE of expected
        // cash, not an absolute amount -- self-scaling across branch
        // sizes rather than guessing a naira figure.
        'high_threshold_pct' => 5.0,    // variance >= 5% of expected cash
        'medium_threshold_pct' => 1.0,  // variance >= 1% of expected cash
        // Below medium_threshold_pct is still alerted (any non-zero
        // variance in a well-controlled cash operation is worth a
        // record), just at LOW severity.
    ],

    'high_reversal' => [
        // Reversals by the same teller within the rolling window below.
        'count_threshold' => 3,
        'window_hours' => 24,
    ],

    'unusual_approval' => [
        // An approval decided faster than this is treated as too fast
        // for genuine review ("rubber-stamping").
        'rubber_stamp_seconds' => 30,
    ],

];
