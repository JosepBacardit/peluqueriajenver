<?php

namespace App\Booking;

use RuntimeException;

/**
 * The same email already holds a confirmed appointment at that time.
 */
class DuplicateAppointmentException extends RuntimeException {}
