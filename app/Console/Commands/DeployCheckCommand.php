<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Run as the web user (deploy.sh does this with `sudo -u www-data`) right
 * before a production deploy finishes: checks the production-safety flags
 * and that PHP-FPM can actually write where it needs to. None of this
 * crashes the site by itself, but it must never reach production silently
 * - see AGENTS.md "Production deploys", which reuses cobaprojects'
 * 2026-10-02 incident as the reason this project gets the same command:
 * APP_DEBUG=true leaked a stack trace to visitors, and
 * storage/framework/views not writable by www-data 500'd every page.
 *
 * Since the online booking system it also checks the outgoing mail
 * configuration and the salon's notification address: the confirmation
 * email carries the customer's only link to cancel, so a site that cannot
 * send mail must not be deployed silently.
 */
#[Signature('deploy:check {--smtp : Also connect and authenticate to the SMTP server, without sending anything}')]
#[Description('Check the production-safety flags, mail settings and writable paths required before a production deploy')]
class DeployCheckCommand extends Command
{
    /**
     * Resolved through storage_path()/bootstrapPath() rather than
     * base_path('storage/...') so the check follows a custom storage path
     * (e.g. $app->useStoragePath() in tests) instead of always the real
     * one.
     *
     * @return array<string, string> label (for output) => absolute path
     */
    private function writablePaths(): array
    {
        return [
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'bootstrap/cache' => $this->laravel->bootstrapPath('cache'),
        ];
    }

    public function handle(): int
    {
        $ok = true;

        if (config('app.env') !== 'production') {
            $this->error('APP_ENV is not "production" (got "'.config('app.env').'").');
            $ok = false;
        } else {
            $this->info('APP_ENV is production.');
        }

        if (config('app.debug') !== false) {
            $this->error('APP_DEBUG is not false.');
            $ok = false;
        } else {
            $this->info('APP_DEBUG is false.');
        }

        $appUrl = (string) config('app.url');
        if (! str_starts_with($appUrl, 'https://')) {
            $this->error("APP_URL does not start with https:// (got \"{$appUrl}\").");
            $ok = false;
        } else {
            $this->info('APP_URL is https.');
        }

        // Every booking email carries this name as its sender and in its
        // branded theme (see resources/views/vendor/mail); the skeleton
        // default would ship "Laravel" to every customer.
        if (config('app.name') === 'Laravel') {
            $this->error('APP_NAME is still the skeleton default ("Laravel"): booking emails must carry the salon\'s name.');
            $ok = false;
        } else {
            $this->info('APP_NAME is set.');
        }

        // The admin panel's session cookie carries access to personal data.
        if (config('session.secure') !== true) {
            $this->error('SESSION_SECURE_COOKIE is not true: the session cookie could travel over plain http.');
            $ok = false;
        } else {
            $this->info('SESSION_SECURE_COOKIE is true.');
        }

        if ($this->option('smtp')) {
            $ok = $this->checkSmtpConnection() && $ok;
        }

        $ok = $this->checkMail() && $ok;

        foreach ($this->writablePaths() as $label => $path) {
            if (! is_writable($path)) {
                $this->error("{$label} is missing or not writable by this user.");
                $ok = false;
            } else {
                $this->info("{$label} is writable.");
            }
        }

        if (! $ok) {
            return self::FAILURE;
        }

        $this->info('Every deploy check passed.');

        return self::SUCCESS;
    }

    /**
     * Opens (and closes) a real connection to the configured mailer, which
     * for SMTP includes the login, so wrong credentials are caught before
     * every booking email starts failing silently. Run it by hand on the
     * first deploy: `php artisan deploy:check --smtp`.
     */
    private function checkSmtpConnection(): bool
    {
        try {
            $transport = Mail::mailer()->getSymfonyTransport();

            if (method_exists($transport, 'start')) {
                $transport->start();
                $transport->stop();
            }
        } catch (Throwable $exception) {
            $this->error('SMTP connection or login failed: '.$exception->getMessage());

            return false;
        }

        $this->info('SMTP connection and login succeeded.');

        return true;
    }

    /**
     * Booking emails must really leave the server: no log/array mailer, a
     * real SMTP host, a sender other than the skeleton default, and a valid
     * address for the salon's notices.
     */
    private function checkMail(): bool
    {
        $ok = true;
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['', 'log', 'array'], true)) {
            $this->error("MAIL_MAILER does not send real email (got \"{$mailer}\").");
            $ok = false;
        } else {
            $this->info("MAIL_MAILER is {$mailer}.");
        }

        if ($mailer === 'smtp') {
            $host = (string) config('mail.mailers.smtp.host');

            if (in_array($host, ['', '127.0.0.1', 'localhost'], true)) {
                $this->error("MAIL_HOST is not a real SMTP server (got \"{$host}\").");
                $ok = false;
            } else {
                $this->info('MAIL_HOST is set.');
            }
        }

        $from = (string) config('mail.from.address');

        if (! filter_var($from, FILTER_VALIDATE_EMAIL) || $from === 'hello@example.com') {
            $this->error("MAIL_FROM_ADDRESS is missing or still the skeleton default (got \"{$from}\").");
            $ok = false;
        } else {
            $this->info('MAIL_FROM_ADDRESS is set.');
        }

        // The sender name is what the customer sees in her inbox.
        $fromName = trim((string) config('mail.from.name'));

        if (in_array(strtolower($fromName), ['', 'laravel', 'example'], true)) {
            $this->error("MAIL_FROM_NAME is missing or still a placeholder (got \"{$fromName}\"): booking emails must come from the salon's name.");
            $ok = false;
        } else {
            $this->info('MAIL_FROM_NAME is set.');
        }

        if (! filter_var((string) config('booking.salon_notification_email'), FILTER_VALIDATE_EMAIL)) {
            $this->error('BOOKING_NOTIFICATION_EMAIL is missing or not a valid email address.');
            $ok = false;
        } else {
            $this->info('BOOKING_NOTIFICATION_EMAIL is set.');
        }

        return $ok;
    }
}
