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
 * Unlike cobaprojects, this project has no contact form and none of its
 * own required .env values (no CONTACT_ or LEGAL_ keys), so this command
 * only checks the production-safety flags and the writable paths.
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
    ]);
});

test('it passes when every check is satisfied', function () {
    $this->artisan('deploy:check')
        ->assertExitCode(0)
        ->expectsOutputToContain('APP_ENV')
        ->expectsOutputToContain('APP_DEBUG')
        ->expectsOutputToContain('APP_URL')
        ->expectsOutputToContain('storage/framework/views')
        ->expectsOutputToContain('storage/logs')
        ->expectsOutputToContain('storage/framework/cache')
        ->expectsOutputToContain('bootstrap/cache');
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
