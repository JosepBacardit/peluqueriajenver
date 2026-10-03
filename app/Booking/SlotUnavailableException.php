<?php

namespace App\Booking;

use RuntimeException;

/**
 * The requested start time is not (or no longer) available for the service.
 */
class SlotUnavailableException extends RuntimeException {}
