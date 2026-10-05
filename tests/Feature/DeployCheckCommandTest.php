<?php

/**
 * `php artisan deploy:check` is meant to run (as the web user, from
 * deploy.sh) right before a production deploy finishes, so a safety flag
 * left wrong or an unwritable directory fails loudly instead of shipping a
 * broken site - see cobaprojects' 2026-10-02 incident, reused here as the
 * reason this project gets the same command (AGENTS.md "Production
 * deploys"): APP_DEBUG=true leaked a stack trace to visitors, and
 * storage/framework/views not writable by www-data 500'd every page.
 *
 * Since the online booking system, it also checks that outgoing mail is
 * really configured (booking confirmations carry the customer's only link
 * to cancel) and that the salon's notification address is set.
 */

/**
 * Creates an empty temporary directory to use as the app's storage path
 * for a test. Never touches the real storage/ directory.
 */
function makeTemporaryDeployCheckStoragePath(): string
{
    $path = sys_get_temp_dir().'/peluqueriajenver-deploy-check-'.uniqid();
    mkdir($path, 0777, true);

    return $path;
}

function removeDeployCheckStoragePath(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $entryPath = $path.'/'.$entry;

        if (is_dir($entryPath)) {
            chmod($entryPath, 0777);
            removeDeployCheckStoragePath($entryPath);
        } else {
            unlink($entryPath);
        }
    }

    rmdir($path);
}

beforeEach(function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://www.peluqueriajenver.com',
        'app.name' => 'Peluquería Jenver',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.mail-provider.example',
        'mail.from.address' => 'citas@example.test',
        'booking.salon_notification_email' => 'salon@example.test',
        'session.secure' => true,
    ]);
});

test('it passes when every check is satisfied', function () {
    $this->artisan('deploy:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('MAIL_MAILER')
        ->expectsOutputToContain('MAIL_FROM_ADDRESS')
        ->expectsOutputToContain('BOOKING_NOTIFICATION_EMAIL')
        ->expectsOutputToContain('APP_ENV')
        ->expectsOutputToContain('APP_DEBUG')
        ->expectsOutputToContain('APP_URL')
        ->expectsOutputToContain('APP_NAME')
        ->expectsOutputToContain('storage/framework/views')
        ->expectsOutputToContain('storage/logs')
        ->expectsOutputToContain('storage/framework/cache')
        ->expectsOutputToContain('bootstrap/cache');
});

test('it fails when outgoing mail is not really configured', function (array $mailConfig, string $expectedOutput) {
    config($mailConfig);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain($expectedOutput);
})->with([
    'mailer writes to the log' => [['mail.default' => 'log'], 'MAIL_MAILER'],
    'mailer keeps mail in memory' => [['mail.default' => 'array'], 'MAIL_MAILER'],
    'smtp without host' => [['mail.mailers.smtp.host' => ''], 'MAIL_HOST'],
    'smtp pointing at the local default' => [['mail.mailers.smtp.host' => '127.0.0.1'], 'MAIL_HOST'],
    'no sender address' => [['mail.from.address' => null], 'MAIL_FROM_ADDRESS'],
    'skeleton sender address' => [['mail.from.address' => 'hello@example.com'], 'MAIL_FROM_ADDRESS'],
    'no salon address' => [['booking.salon_notification_email' => null], 'BOOKING_NOTIFICATION_EMAIL'],
    'invalid salon address' => [['booking.salon_notification_email' => 'salon-at-example'], 'BOOKING_NOTIFICATION_EMAIL'],
]);

test('it fails when the app name is still the skeleton default', function () {
    config(['app.name' => 'Laravel']);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('APP_NAME');
});

test('it fails when app env is not production', function () {
    config(['app.env' => 'local']);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('APP_ENV');
});

test('it fails when app debug is true', function () {
    config(['app.debug' => true]);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('APP_DEBUG');
});

test('it fails when app url is not https', function () {
    config(['app.url' => 'http://www.peluqueriajenver.com']);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('APP_URL');
});

test('it fails when a required storage path is missing', function () {
    $tempStoragePath = makeTemporaryDeployCheckStoragePath();
    mkdir($tempStoragePath.'/framework/cache', 0777, true);
    mkdir($tempStoragePath.'/logs', 0777, true);
    // Deliberately not created: framework/views.

    $this->app->useStoragePath($tempStoragePath);
    $this->beforeApplicationDestroyed(fn () => removeDeployCheckStoragePath($tempStoragePath));

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('storage/framework/views');
});

/**
 * Regression guard: the storage paths must be resolved through
 * storage_path()/bootstrapPath() rather than base_path('storage/...'), so
 * the check follows a custom storage path (as set here) instead of always
 * the real one - see cobaprojects' .ai/reviews/deploy-safety.md Finding 7,
 * applied here from the start rather than as a later fix.
 *
 * Skipped when run as root (this project's Docker container does),
 * because root bypasses filesystem permission checks and is_writable()
 * would always be true.
 */
test('it fails when a required storage path exists but is not writable', function () {
    if (posix_geteuid() === 0) {
        $this->markTestSkipped('Running as root bypasses filesystem permission checks.');
    }

    $tempStoragePath = makeTemporaryDeployCheckStoragePath();
    mkdir($tempStoragePath.'/framework/views', 0777, true);
    mkdir($tempStoragePath.'/framework/cache', 0777, true);
    mkdir($tempStoragePath.'/logs', 0777, true);
    chmod($tempStoragePath.'/framework/views', 0555);

    $this->app->useStoragePath($tempStoragePath);
    $this->beforeApplicationDestroyed(fn () => removeDeployCheckStoragePath($tempStoragePath));

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('storage/framework/views');
});

test('it fails when the session cookie is not https-only', function () {
    config(['session.secure' => null]);

    $this->artisan('deploy:check')
        ->assertExitCode(1)
        ->expectsOutputToContain('SESSION_SECURE_COOKIE');
});

test('with --smtp it fails when the smtp server cannot be reached', function () {
    config([
        'mail.mailers.smtp.host' => 'smtp.invalid',
        'mail.mailers.smtp.port' => 25,
        'mail.mailers.smtp.timeout' => 2,
    ]);

    $this->artisan('deploy:check --smtp')
        ->assertExitCode(1)
        ->expectsOutputToContain('SMTP');
});

test('outgoing mail has a finite timeout so a dead smtp server cannot hang a booking', function () {
    $timeout = config('mail.mailers.smtp.timeout');

    expect($timeout)->toBeNumeric();
    expect((int) $timeout)->toBeGreaterThan(0)->toBeLessThanOrEqual(15);
});

test('deploy.sh refuses to start when the .env lacks the booking mail settings', function (string $env, int $expectedExit) {
    $envFile = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($envFile, $env);

    // Extract only the function from deploy.sh and run it with plain sh.
    preg_match('/^check_env_mail\(\) \{.*?^\}/ms',file_get_contents(base_path('deploy.sh')), $function);
    $functionFile = tempnam(sys_get_temp_dir(), 'fn');
    file_put_contents($functionFile, $function[0]);

    exec('sh -c '.escapeshellarg('. '.escapeshellarg($functionFile).'; check_env_mail '.escapeshellarg($envFile)).' 2>&1', $output, $exitCode);
    unlink($envFile);
    unlink($functionFile);

    expect($exitCode)->toBe($expectedExit, implode("\n", $output));
})->with([
    'complete' => ["MAIL_MAILER=smtp\nMAIL_HOST=smtp.mail-provider.example\nMAIL_FROM_ADDRESS=\"citas@example.test\"\nBOOKING_NOTIFICATION_EMAIL=salon@example.test\n", 0],
    'log mailer' => ["MAIL_MAILER=log\nMAIL_HOST=smtp.mail-provider.example\nMAIL_FROM_ADDRESS=citas@example.test\nBOOKING_NOTIFICATION_EMAIL=salon@example.test\n", 1],
    'missing salon address' => ["MAIL_MAILER=smtp\nMAIL_HOST=smtp.mail-provider.example\nMAIL_FROM_ADDRESS=citas@example.test\nBOOKING_NOTIFICATION_EMAIL=\n", 1],
    'skeleton sender' => ["MAIL_MAILER=smtp\nMAIL_HOST=smtp.mail-provider.example\nMAIL_FROM_ADDRESS=\"hello@example.com\"\nBOOKING_NOTIFICATION_EMAIL=salon@example.test\n", 1],
    'missing host' => ["MAIL_MAILER=smtp\nMAIL_FROM_ADDRESS=citas@example.test\nBOOKING_NOTIFICATION_EMAIL=salon@example.test\n", 1],
]);
