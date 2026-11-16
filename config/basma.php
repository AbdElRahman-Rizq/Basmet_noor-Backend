<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial SUPER_ADMIN account (seeded from environment only)
    |--------------------------------------------------------------------------
    |
    | No credentials are hardcoded. If the password or email is missing the
    | seeder skips account creation and reports it in the console.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'mobile' => env('SUPER_ADMIN_MOBILE'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

];
