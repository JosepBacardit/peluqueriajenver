<?php

namespace App\Booking;

use RuntimeException;

/**
 * The appointment was changed by someone else after the edit form being
 * sent was opened, so saving it would silently undo that change.
 */
class AppointmentChangedException extends RuntimeException {}
