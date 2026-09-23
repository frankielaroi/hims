<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Waiving charges for exempt groups
    |--------------------------------------------------------------------------
    |
    | Ghana's NHIS exempt groups — pregnant women, under-fives, the elderly,
    | indigents — are exempt from PREMIUMS and are covered under the scheme.
    | That is not the same as every service being free at every facility, and
    | which services a facility waives at the desk is its own policy.
    |
    | So the exemption is always recorded and every affected line is flagged,
    | but the money is only zeroed when a facility switches this on.
    |
    */

    'waive_exempt_charges' => env('BILLING_WAIVE_EXEMPT_CHARGES', false),

];
