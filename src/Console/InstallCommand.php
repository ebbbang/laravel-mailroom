<?php

namespace Ebbbang\TestMail\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'test-mail:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish the test-mail configuration and prepare the mailbox';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->warn(
                'APP_ENV is production. This package stays disabled here unless you set TEST_MAIL_ENABLED=true, '
                .'which would capture real mail instead of delivering it.'
            );
        }

        $this->comment('Publishing configuration...');
        $this->callSilently('vendor:publish', [
            '--tag' => 'test-mail-config',
            '--force' => $this->option('force'),
        ]);

        if ($this->shouldConfigureEnv()) {
            $this->setEnvMailer();
        }

        $this->newLine();
        $this->components->info('laravel-test-mail installed.');

        $this->components->bulletList([
            'Set MAIL_MAILER=database to start capturing mail.',
            'Run `php artisan migrate` to create the tables.',
            'Visit /'.config('test-mail.path', 'test-mail').' to read what you send.',
            'Outside local, define a viewTestMail gate to grant access.',
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

        return confirm('Set MAIL_MAILER=database in your .env now?', default: false);
    }

    protected function setEnvMailer(): void
    {
        $path = $this->laravel->basePath('.env');
        $contents = (string) file_get_contents($path);

        $updated = preg_match('/^MAIL_MAILER=.*$/m', $contents)
            ? preg_replace('/^MAIL_MAILER=.*$/m', 'MAIL_MAILER=database', $contents)
            : rtrim($contents, "\n")."\nMAIL_MAILER=database\n";

        file_put_contents($path, $updated);

        $this->components->info('Set MAIL_MAILER=database in .env.');
    }
}
