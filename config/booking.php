<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Salon notification address
    |--------------------------------------------------------------------------
    |
    | Where the salon receives notices of new online bookings and of
    | cancellations made by customers. Required in production:
    | `php artisan deploy:check` fails without a valid address.
    |
    */

    'salon_notification_email' => env('BOOKING_NOTIFICATION_EMAIL'),

];
