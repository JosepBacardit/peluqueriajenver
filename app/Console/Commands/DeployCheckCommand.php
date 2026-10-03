<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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
#[Signature('deploy:check')]
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

        if (! filter_var((string) config('booking.salon_notification_email'), FILTER_VALIDATE_EMAIL)) {
            $this->error('BOOKING_NOTIFICATION_EMAIL is missing or not a valid email address.');
            $ok = false;
        } else {
            $this->info('BOOKING_NOTIFICATION_EMAIL is set.');
        }

        return $ok;
    }
}
