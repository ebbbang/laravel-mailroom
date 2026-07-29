<?php

namespace Ebbbang\TestMail;

use Ebbbang\TestMail\Console\ClearCommand;
use Ebbbang\TestMail\Console\InstallCommand;
use Ebbbang\TestMail\Console\PruneCommand;
use Ebbbang\TestMail\Recording\MessageRecorder;
use Ebbbang\TestMail\Storage\RawMessageStore;
use Ebbbang\TestMail\Transport\TransportFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TestMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/test-mail.php', 'test-mail');

        $this->registerMailerConfig();

        $this->app->singleton(RawMessageStore::class);
        $this->app->singleton(MessageRecorder::class);
        $this->app->singleton(TransportFactory::class);
    }

    public function boot(): void
    {
        $this->registerTransport();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerSchedule();
    }

    /**
     * Teach the mail manager about the "database" transport.
     *
     * Deferred via callAfterResolving so merely booting this provider does not
     * force the mail manager into existence for applications that never send
     * mail on a given request.
     */
    protected function registerTransport(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $manager): void {
            $manager->extend('database', fn (array $config) => $this->app
                ->make(TransportFactory::class)
                ->make($config));
        });
    }

    /**
     * Define a "database" mailer up front so MAIL_MAILER=database works
     * without the consumer editing config/mail.php at all.
     */
    protected function registerMailerConfig(): void
    {
        $config = $this->app->make('config');

        if ($config->get('mail.mailers.database') === null) {
            $config->set('mail.mailers.database', ['transport' => 'database']);
        }
    }

    /**
     * When the package is disabled the routes are never registered at all, so
     * the mailbox 404s rather than 403s. No route means no exposure surface.
     */
    protected function registerRoutes(): void
    {
        if (! TestMail::enabled()) {
            return;
        }

        Route::group([
            'domain' => config('test-mail.domain'),
            'prefix' => config('test-mail.path', 'test-mail'),
            'middleware' => config('test-mail.middleware', ['web']),
            'as' => 'test-mail.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'test-mail');

        // Only create the tables where the package is actually usable, so a
        // production deploy with TEST_MAIL_ENABLED unset gains no new tables.
        if (TestMail::enabled()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/test-mail.php' => config_path('test-mail.php'),
        ], 'test-mail-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'test-mail-migrations');

        // Styles are inlined into the layout rather than published, so there
        // is no build step for consumers and no stale-asset failure mode.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/test-mail'),
        ], 'test-mail-views');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ClearCommand::class,
            InstallCommand::class,
            PruneCommand::class,
        ]);
    }

    /**
     * Opt-in scheduling: only wires itself up when the consumer sets a
     * schedule, so we never add work to an application that did not ask.
     */
    protected function registerSchedule(): void
    {
        $expression = config('test-mail.prune.schedule');

        if (blank($expression) || ! TestMail::enabled()) {
            return;
        }

        $this->app->booted(function () use ($expression): void {
            $event = $this->app->make(Schedule::class)->command(PruneCommand::class);

            method_exists($event, (string) $expression)
                ? $event->{$expression}()
                : $event->cron((string) $expression);
        });
    }
}
