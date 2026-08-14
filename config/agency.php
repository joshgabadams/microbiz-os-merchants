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

];