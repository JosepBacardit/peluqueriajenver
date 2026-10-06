<?php

namespace Tests;

use App\Booking\OpeningHoursSummary;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * OpeningHoursSummary's per-request cache is a plain static property
     * (fine in production: php-fpm starts a fresh process per request),
     * but the whole test suite shares one PHP process. Without this, a
     * test that changes the schedule directly through the OpeningHour
     * model (not through OpeningHoursController::update(), the only place
     * that clears it in application code) would leak its cached result
     * into whichever test happens to run next.
     */
    protected function setUp(): void
    {
        parent::setUp();

        OpeningHoursSummary::forgetCachedSchedule();
    }
}
