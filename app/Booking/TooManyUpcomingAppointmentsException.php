<?php

namespace App\Booking;

use RuntimeException;

/**
 * The customer's email or phone already holds the maximum number of
 * upcoming confirmed online appointments.
 */
class TooManyUpcomingAppointmentsException extends RuntimeException {}
