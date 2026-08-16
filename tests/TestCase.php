<?php

namespace Ebbbang\Mailroom\Tests;

use Ebbbang\Mailroom\Mailroom;
use Ebbbang\Mailroom\MailroomServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mailroom::flushState();

        Storage::fake('local');

        View::addNamespace('mailroom-tests', __DIR__.'/Fixtures/views');
    }

    protected function tearDown(): void
    {
        Mailroom::flushState();

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

        // Pinned rather than inherited: testbench.yaml exists for the
        // workbench demo app, and the suite must not depend on whatever it
        // happens to configure. Sync lets the queued-mailable test assert
        // that the transport captured the message without running a worker.
        $app['config']->set('queue.default', 'sync');

        // Rate limiting runs through the cache, and the skeleton defaults to
        // the database store without a cache table to back it.
        $app['config']->set('cache.default', 'array');

        $app['config']->set('mail.default', 'mailroom');
        $app['config']->set('mail.from', [
            'address' => 'app@example.test',
            'name' => 'Example App',
        ]);

        $app['config']->set('mailroom.enabled', true);
    }
}
