<?php

namespace Ebbbang\Mailroom;

use Ebbbang\Mailroom\Console\ClearCommand;
use Ebbbang\Mailroom\Console\InstallCommand;
use Ebbbang\Mailroom\Console\PruneCommand;
use Ebbbang\Mailroom\Listeners\FlushStorageOnDatabaseRefresh;
use Ebbbang\Mailroom\Recording\MessageRecorder;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Ebbbang\Mailroom\Transport\TransportFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MailroomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailroom.php', 'mailroom');

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
        $this->registerListeners();
        $this->registerRateLimiter();
    }

    /**
     * Bound how often one person may forward a message.
     *
     * A named limiter rather than a bare "throttle:10,1" so a refusal can
     * redirect back with an explanation, landing in the dialog like every
     * other forwarding failure instead of a browser-facing 429.
     *
     * The key has to be set explicitly. ThrottleRequests only falls back to a
     * per-user request signature for the unnamed form; a named limiter keys on
     * md5($name . $limit->key), so leaving that empty puts everyone in one
     * bucket and lets one busy tester lock out the team.
     */
    protected function registerRateLimiter(): void
    {
        RateLimiter::for('mailroom-forward', function (Request $request): Limit {
            $perMinute = config('mailroom.forward.rate_limit');

            if (blank($perMinute) || (int) $perMinute < 1) {
                return Limit::none();
            }

            $identifier = $request->user()?->getAuthIdentifier();

            return Limit::perMinute((int) $perMinute)
                // Prefixed so a user id can never collide with an IP.
                ->by($identifier === null ? 'ip:'.$request->ip() : 'user:'.$identifier)
                ->response(fn (): RedirectResponse => back()->with(
                    'mailroom.error',
                    'That is a lot of forwarding in one minute. Give it a moment and try again.'
                ));
        });
    }

    /**
     * `migrate:fresh` drops tables without firing model events, which is what
     * would otherwise delete a message's blobs along with its row. Listening
     * for the refresh keeps the disk in step with the database.
     *
     * Never during a test run. Laravel's RefreshDatabase trait runs
     * `migrate:fresh` as it boots each test, and it does so before a test has
     * had the chance to call Storage::fake() -- so the listener would fire
     * against the developer's real disk and delete the mail they had captured
     * while working. The refresh there belongs to a throwaway test database,
     * which is no reason to touch their files at all.
     */
    protected function registerListeners(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        Event::listen(DatabaseRefreshed::class, FlushStorageOnDatabaseRefresh::class);
    }

    /**
     * Teach the mail manager about the "mailroom" transport.
     *
     * Deferred via callAfterResolving so merely booting this provider does not
     * force the mail manager into existence for applications that never send
     * mail on a given request.
     *
     * The transport is built from $container -- the container that resolved
     * the mail manager -- rather than from $this->app. Under Octane those are
     * not the same object: providers boot against the base application, while
     * each request runs in a sandbox clone. "mail.manager" is not among
     * Octane's warmed services, so it is built inside the sandbox, and the
     * transport has to follow it there. Capturing $this->app instead would
     * dispatch MessageStored on the base application's event dispatcher while
     * Laravel's own MessageSent fired on the sandbox's, so a listener
     * registered during a request would see one and not the other.
     */
    protected function registerTransport(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $manager, Container $container): void {
            $manager->extend('mailroom', fn (array $config) => $container
                ->make(TransportFactory::class)
                ->make($config));
        });
    }

    /**
     * Define a "mailroom" mailer up front so MAIL_MAILER=mailroom works
     * without the consumer editing config/mail.php at all.
     */
    protected function registerMailerConfig(): void
    {
        $config = $this->app->make('config');

        if ($config->get('mail.mailers.mailroom') === null) {
            $config->set('mail.mailers.mailroom', ['transport' => 'mailroom']);
        }
    }

    /**
     * When the package is disabled the routes are never registered at all, so
     * the mailbox 404s rather than 403s. No route means no exposure surface.
     */
    protected function registerRoutes(): void
    {
        if (! Mailroom::enabled()) {
            return;
        }

        Route::group([
            'domain' => config('mailroom.domain'),
            'prefix' => config('mailroom.path', 'mailroom'),
            'middleware' => config('mailroom.middleware', ['web']),
            'as' => 'mailroom.',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mailroom');

        // Only create the tables where the package is actually usable, so a
        // production deploy with MAILROOM_ENABLED unset gains no new tables.
        if (Mailroom::enabled()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/mailroom.php' => config_path('mailroom.php'),
        ], 'mailroom-config');

        // The migrations are deliberately not publishable. loadMigrationsFrom
        // above is gated on Mailroom::enabled(), and a published copy sitting
        // in database/migrations would run regardless -- handing a production
        // deploy the tables that gate exists to withhold. Table names and the
        // connection are already config-driven, so the only thing publishing
        // would buy is schema edits, and those belong in your own ALTER
        // migration where they neither collide nor leak into production.

        // Styles are inlined into the layout rather than published, so there
        // is no build step for consumers and no stale-asset failure mode.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mailroom'),
        ], 'mailroom-views');
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
        $expression = config('mailroom.prune.schedule');

        if (blank($expression) || ! Mailroom::enabled()) {
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
