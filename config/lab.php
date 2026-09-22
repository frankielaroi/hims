<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Second-person authorisation
    |--------------------------------------------------------------------------
    |
    | A Medical Laboratory Scientist is licensed to authorise a result they
    | produced themselves, so this is off by default. Facilities that want a
    | strict four-eyes rule — the person who entered a result may never be the
    | person who authorises it — can switch it on.
    |
    */

    'require_second_person' => env('LAB_REQUIRE_SECOND_PERSON', false),

];
