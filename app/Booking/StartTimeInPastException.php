<?php

namespace App\Booking;

use RuntimeException;

/**
 * The requested start time has already passed. Unlike a full or closed
 * time, the salon can never override this.
 */
class StartTimeInPastException extends RuntimeException {}
