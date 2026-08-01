<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\MailroomServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Concerns\InteractsWithPublishedFiles;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\Test;

/**
 * Deliberately not extending the package TestCase: that one uses
 * RefreshDatabase, which migrates during setUp, and these tests need to
 * observe a database where the mailroom tables do *not* exist yet.
 *
 * Two routes through the command are covered: the flags, which is how CI and
 * Dockerfiles will drive it, and the prompts, which is what a person sees.
 */
class InstallCommandTest extends Orchestra
{
    use InteractsWithPublishedFiles;

    private const ENV_PROMPT = 'Set MAIL_MAILER=mailroom in your .env now?';

    private const MIGRATE_PROMPT = 'Run migrations now? This runs every pending migration in your application, not only the Mailroom ones.';

    /** @var array<int, string> */
    protected array $files = ['config/mailroom.php'];

    private ?string $envPath = null;

    private ?string $envBackup = null;

    private bool $envExisted = false;

    protected function tearDown(): void
    {
        $this->restoreEnvFile();

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MailroomServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('mailroom.enabled', true);
    }

    #[Test]
    public function it_publishes_the_configuration_file(): void
    {
        $this->assertFilenameDoesNotExists('config/mailroom.php');

        $this->artisan('mailroom:install', $this->unattended(['--no-migrate' => true]))
            ->assertSuccessful();

        $this->assertFilenameExists('config/mailroom.php');
    }

    #[Test]
    public function it_can_skip_publishing_the_configuration(): void
    {
        $this->artisan('mailroom:install', $this->unattended(['--no-config' => true, '--no-migrate' => true]))
            ->assertSuccessful();

        // Nothing to publish is the normal case: the config is merged from the
        // package, so a consumer only needs the file to change something.
        $this->assertFilenameDoesNotExists('config/mailroom.php');
    }

    #[Test]
    public function it_runs_the_migrations_when_asked(): void
    {
        $this->assertFalse(Schema::hasTable('mailroom_messages'));
        $this->assertFalse(Schema::hasTable('mailroom_attachments'));

        $this->artisan('mailroom:install', $this->unattended(['--no-config' => true, '--migrate' => true]))
            ->assertSuccessful();

        $this->assertTrue(Schema::hasTable('mailroom_messages'));
        $this->assertTrue(Schema::hasTable('mailroom_attachments'));
    }

    #[Test]
    public function it_leaves_the_database_alone_when_migrations_are_declined(): void
    {
        $this->artisan('mailroom:install', $this->unattended(['--no-config' => true, '--no-migrate' => true]))
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('mailroom_messages'));
    }

    #[Test]
    public function it_refuses_to_migrate_while_the_package_is_disabled(): void
    {
        // The migration path was registered at boot, so without the guard in
        // the command this call would happily create the tables -- which is
        // the hole being closed: a disabled Mailroom must not add tables.
        config()->set('mailroom.enabled', false);

        $this->artisan('mailroom:install', $this->unattended(['--no-config' => true, '--migrate' => true]))
            ->expectsOutputToContain('Skipping migrations')
            ->assertSuccessful();

        $this->assertFalse(Schema::hasTable('mailroom_messages'));
    }

    #[Test]
    public function it_does_nothing_to_the_env_file_or_the_database_when_both_prompts_are_declined(): void
    {
        $path = $this->useEnvFile("MAIL_MAILER=smtp\n");

        $this->artisan('mailroom:install', ['--no-config' => true])
            ->expectsConfirmation(self::ENV_PROMPT, 'no')
            ->expectsConfirmation(self::MIGRATE_PROMPT, 'no')
            ->assertSuccessful();

        $this->assertSame("MAIL_MAILER=smtp\n", (string) file_get_contents($path));
        $this->assertFalse(Schema::hasTable('mailroom_messages'));
    }

    #[Test]
    public function accepting_the_prompts_writes_the_env_file_and_migrates(): void
    {
        $path = $this->useEnvFile("MAIL_MAILER=smtp\n");

        $this->artisan('mailroom:install', ['--no-config' => true])
            ->expectsConfirmation(self::ENV_PROMPT, 'yes')
            ->expectsConfirmation(self::MIGRATE_PROMPT, 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString('MAIL_MAILER=mailroom', (string) file_get_contents($path));
        $this->assertTrue(Schema::hasTable('mailroom_messages'));
    }

    #[Test]
    public function it_replaces_an_existing_mail_mailer_line_rather_than_appending(): void
    {
        $path = $this->useEnvFile("APP_NAME=Testbench\nMAIL_MAILER=smtp\nQUEUE_CONNECTION=sync\n");

        $this->artisan('mailroom:install', $this->unattended([
            '--no-config' => true,
            '--no-migrate' => true,
            '--set-mailer' => true,
        ]))->assertSuccessful();

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('MAIL_MAILER=mailroom', $contents);
        $this->assertStringNotContainsString('MAIL_MAILER=smtp', $contents);

        // A second MAIL_MAILER line would leave the file with two, and which
        // one wins depends on the parser.
        $this->assertSame(1, substr_count($contents, 'MAIL_MAILER='));

        // Untouched neighbours, so the rewrite is a replacement rather than a
        // truncation.
        $this->assertStringContainsString('APP_NAME=Testbench', $contents);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $contents);
    }

    #[Test]
    public function it_appends_the_mailer_when_the_env_file_has_no_mail_mailer_line(): void
    {
        $path = $this->useEnvFile("APP_NAME=Testbench\n");

        $this->artisan('mailroom:install', $this->unattended([
            '--no-config' => true,
            '--no-migrate' => true,
            '--set-mailer' => true,
        ]))->assertSuccessful();

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('APP_NAME=Testbench', $contents);
        $this->assertSame(1, substr_count($contents, 'MAIL_MAILER=mailroom'));
    }

    #[Test]
    public function it_leaves_the_env_file_alone_when_run_unattended_without_the_flag(): void
    {
        $path = $this->useEnvFile("MAIL_MAILER=smtp\n");

        $this->artisan('mailroom:install', $this->unattended(['--no-config' => true, '--no-migrate' => true]))
            ->assertSuccessful();

        $this->assertSame("MAIL_MAILER=smtp\n", (string) file_get_contents($path));
    }

    #[Test]
    public function the_migrations_are_not_publishable(): void
    {
        $groups = ServiceProvider::publishableGroups();

        $this->assertContains('mailroom-config', $groups);
        $this->assertContains('mailroom-views', $groups);

        // Publishing them would put a copy in database/migrations, which runs
        // regardless of mailroom.enabled and so hands a production deploy the
        // tables that gate exists to withhold.
        $this->assertNotContains('mailroom-migrations', $groups);
    }

    /**
     * Run without a TTY, the way CI would.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function unattended(array $options = []): array
    {
        return $options + ['--no-interaction' => true];
    }

    /**
     * Swap in a throwaway .env, remembering the real one.
     *
     * The Testbench skeleton ships a populated .env that the workbench demo
     * relies on, so these tests must put it back rather than delete it.
     */
    private function useEnvFile(string $contents): string
    {
        $this->envPath = $this->app->basePath('.env');
        $this->envExisted = file_exists($this->envPath);
        $this->envBackup = $this->envExisted ? (string) file_get_contents($this->envPath) : null;

        file_put_contents($this->envPath, $contents);

        return $this->envPath;
    }

    private function restoreEnvFile(): void
    {
        if ($this->envPath === null) {
            return;
        }

        $this->envExisted
            ? file_put_contents($this->envPath, (string) $this->envBackup)
            : @unlink($this->envPath);

        $this->envPath = null;
        $this->envBackup = null;
        $this->envExisted = false;
    }
}
