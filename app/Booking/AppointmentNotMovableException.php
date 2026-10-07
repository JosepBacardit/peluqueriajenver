<?php

namespace App\Booking;

use RuntimeException;

/**
 * The appointment is no longer confirmed or has already started, so it
 * cannot be moved.
 */
class AppointmentNotMovableException extends RuntimeException {}
