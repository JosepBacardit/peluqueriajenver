<?php

namespace App\Booking;

use RuntimeException;

/**
 * The requested start time is not (or no longer) available for the service.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(public readonly ?UnavailabilityReason $reason = null)
    {
        parent::__construct($reason === null ? 'Slot unavailable.' : 'Slot unavailable: '.$reason->value.'.');
    }
}
