<?php

// config/wallet.php
//
// CBN three-tiered KYC limits for mobile-money-style wallets.
//
// IMPORTANT: sources consulted gave slightly different figures for Tier 1/2
// (e.g. some cite a NGN 50,000 daily / NGN 300,000 cumulative-balance Tier 1,
// others cite NGN 30,000 daily / NGN 300,000). The tiered *structure* itself
// (3 tiers, BVN/NIN requirements, balance caps) is well established, but
// CONFIRM THE EXACT CURRENT FIGURES WITH COMPLIANCE before relying on these
// in production -- CBN circulars amend these limits periodically.
//
// 'daily_limit' is the daily cumulative OUTFLOW limit (transfers out), per
// CBN's own framing -- it does not apply to top-ups (inbound). 'balance_cap'
// applies to the wallet's total balance regardless of direction. null means
// no cap (Tier 3).

return [

    'tiers' => [
        1 => [
            'label' => 'Tier 1',
            'daily_limit' => 50000,
            'balance_cap' => 300000,
            'requires' => 'bvn_or_nin',
        ],
        2 => [
            'label' => 'Tier 2',
            'daily_limit' => 200000,
            'balance_cap' => 500000,
            'requires' => 'bvn_and_nin',
        ],
        3 => [
            'label' => 'Tier 3',
            'daily_limit' => 5000000,
            'balance_cap' => null,
            'requires' => 'bvn_and_nin',
        ],
    ],

];
