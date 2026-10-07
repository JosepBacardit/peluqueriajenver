<?php

namespace Tests;

use App\Booking\OpeningHoursSummary;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

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
     *
     * Cache::flush() guards against the same kind of leak for anything
     * backed by the (in-memory, process-wide) `array` cache store used in
     * testing — chiefly RateLimiter. RefreshDatabase rolls the database
     * back between tests, but SQLite (the test connection) reuses ids
     * once a table is empty again, so a rate limiter keyed by a user id
     * (e.g. "password-change:1") can otherwise carry a count left by a
     * completely unrelated earlier test straight into a later one that
     * happens to create its own id-1 user, long before this test's own
     * logic ever runs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        OpeningHoursSummary::forgetCachedSchedule();
        Cache::flush();
    }
}
