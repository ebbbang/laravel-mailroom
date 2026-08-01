<?php

namespace Ebbbang\Mailroom\Console;

use Ebbbang\Mailroom\Mailroom;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * A convenience, not a requirement. The package works the moment Composer
 * finishes: the config is merged, the migrations are loaded from the package
 * itself, and a "mailroom" mailer is registered. Everything this command does
 * can be done by hand, and nothing it publishes is needed unless you intend to
 * change it.
 *
 * Every prompt has a matching flag, so the whole command can run unattended
 * in CI or a Dockerfile without a TTY.
 */
class InstallCommand extends Command
{
    protected $signature = 'mailroom:install
        {--force : Overwrite any existing published files}
        {--no-config : Skip publishing the configuration file}
        {--set-mailer : Set MAIL_MAILER=mailroom in .env without asking}
        {--migrate : Run database migrations without asking}
        {--no-migrate : Skip database migrations without asking}';

    protected $description = 'Publish the mailroom configuration and prepare the mailbox';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->warn(
                'APP_ENV is production. This package stays disabled here unless you set MAILROOM_ENABLED=true, '
                .'which would capture real mail instead of delivering it.'
            );
        }

        if (! $this->option('no-config')) {
            $this->comment('Publishing configuration...');
            $this->callSilently('vendor:publish', [
                '--tag' => 'mailroom-config',
                '--force' => $this->option('force'),
            ]);
        }

        $mailerSet = $this->shouldConfigureEnv();

        if ($mailerSet) {
            $this->setEnvMailer();
        }

        $migrated = $this->handleMigrations();

        $this->newLine();
        $this->components->info('laravel-mailroom installed.');

        $this->components->bulletList(array_values(array_filter([
            $mailerSet ? null : 'Set MAIL_MAILER=mailroom to start capturing mail.',
            $migrated ? null : 'Run `php artisan migrate` to create the tables.',
            'Visit /'.config('mailroom.path', 'mailroom').' to read what you send.',
            'Outside local, define a viewMailroom gate to grant access.',
        ])));

        return self::SUCCESS;
    }

    protected function shouldConfigureEnv(): bool
    {
        if (! file_exists($this->laravel->basePath('.env'))) {
            return false;
        }

        if ($this->option('set-mailer')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Set MAIL_MAILER=mailroom in your .env now?', default: false);
    }

    protected function setEnvMailer(): void
    {
        $path = $this->laravel->basePath('.env');
        $contents = (string) file_get_contents($path);

        $updated = preg_match('/^MAIL_MAILER=.*$/m', $contents)
            ? preg_replace('/^MAIL_MAILER=.*$/m', 'MAIL_MAILER=mailroom', $contents)
            : rtrim($contents, "\n")."\nMAIL_MAILER=mailroom\n";

        file_put_contents($path, $updated);

        $this->components->info('Set MAIL_MAILER=mailroom in .env.');
    }

    /**
     * @return bool whether migrations were actually run
     */
    protected function handleMigrations(): bool
    {
        if ($this->option('no-migrate')) {
            return false;
        }

        // The migrations are only registered while the package is enabled, so
        // migrating here could not create the mailroom tables. It would run
        // the application's other pending migrations and nothing else, which
        // is not what anyone asked for by typing "mailroom:install".
        if (! Mailroom::enabled()) {
            $this->components->warn(
                'Skipping migrations: Mailroom is disabled, so its migrations are not registered. '
                .'Set MAILROOM_ENABLED=true, then run `php artisan migrate`.'
            );

            return false;
        }

        if (! $this->shouldMigrate()) {
            return false;
        }

        // Consent has already been given, either by the flag or at the prompt,
        // so --force stops migrate asking a second time on a production
        // connection.
        $this->call('migrate', ['--force' => true]);

        return true;
    }

    protected function shouldMigrate(): bool
    {
        if ($this->option('migrate')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        // Worth spelling out: "migrate" is not scoped to this package, and on
        // a shared database the pending set may be someone else's work.
        return confirm(
            'Run migrations now? This runs every pending migration in your application, not only the Mailroom ones.',
            default: true,
        );
    }
}
