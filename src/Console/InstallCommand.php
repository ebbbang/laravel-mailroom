<?php

namespace Ebbbang\Mailroom\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'mailroom:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish the mailroom configuration and prepare the mailbox';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->warn(
                'APP_ENV is production. This package stays disabled here unless you set MAILROOM_ENABLED=true, '
                .'which would capture real mail instead of delivering it.'
            );
        }

        $this->comment('Publishing configuration...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'mailroom-config',
            '--force' => $this->option('force'),
        ]);

        if ($this->shouldConfigureEnv()) {
            $this->setEnvMailer();
        }

        $this->newLine();
        $this->components->info('laravel-mailroom installed.');

        $this->components->bulletList([
            'Set MAIL_MAILER=mailroom to start capturing mail.',
            'Run `php artisan migrate` to create the tables.',
            'Visit /'.config('mailroom.path', 'mailroom').' to read what you send.',
            'Outside local, define a viewMailroom gate to grant access.',
        ]);

        return self::SUCCESS;
    }

    protected function shouldConfigureEnv(): bool
    {
        if (! file_exists($this->laravel->basePath('.env'))) {
            return false;
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
}
