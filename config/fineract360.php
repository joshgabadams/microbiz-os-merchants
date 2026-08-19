<?php

/**
 * Deliberately separate from config/fineract.php: that config is reserved
 * for the live Fincore integration. Fincore360 is a non-live mirror used
 * for building/testing against (merchant registration lookup, chart of
 * accounts, etc.) -- keeping the two under different keys means wiring up
 * the real Fincore later never requires renaming anything already in use.
 */
return [

    'url' => env('FINERACT360_URL'),

    'tenant' => env('FINERACT360_TENANT'),

    'user' => env('FINERACT360_USER'),

    'password' => env('FINERACT360_PASSWORD'),

];
