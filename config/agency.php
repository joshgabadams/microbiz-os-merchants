<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Terminal Controls
    |--------------------------------------------------------------------------
    |
    | Customer-facing agency transactions require the assigned terminal
    | to have communicated with the platform recently.
    |
    | A terminal whose heartbeat is older than this threshold is treated
    | as unavailable for transaction processing until it heartbeats again.
    |
    */

    'terminal' => [

        'heartbeat_timeout_seconds' => (int) env(
            'AGENT_TERMINAL_HEARTBEAT_TIMEOUT_SECONDS',
            300
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Risk Controls
    |--------------------------------------------------------------------------
    |
    | These settings control deterministic transaction-risk assessment for
    | agency transactions. They are deliberately separate from operational
    | guards such as KYC, agreements, terminal status and transaction limits.
    |
    */

    'risk' => [

        /*
         * Number of minutes considered when evaluating recent successful
         * transaction velocity for an agent.
         */
        'velocity_window_minutes' => (int) env(
            'AGENT_RISK_VELOCITY_WINDOW_MINUTES',
            10
        ),

        /*
         * Number of COMPLETED transactions within the velocity window that
         * causes the transaction-velocity risk rule to match.
         */
        'velocity_transaction_threshold' => (int) env(
            'AGENT_RISK_VELOCITY_TRANSACTION_THRESHOLD',
            10
        ),

        /*
         * Aggregate score thresholds used to classify transaction risk.
         */
        'medium_score_threshold' => (int) env(
            'AGENT_RISK_MEDIUM_SCORE_THRESHOLD',
            25
        ),

        'high_score_threshold' => (int) env(
            'AGENT_RISK_HIGH_SCORE_THRESHOLD',
            60
        ),

    ],

];