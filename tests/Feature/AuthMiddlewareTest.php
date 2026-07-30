<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Http\Middleware\Authorize;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

/**
 * The package ships no login page of its own. Consumers who want the mailbox
 * behind authentication add "auth" to the middleware stack and the
 * application's existing login flow takes over.
 */
class AuthMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mailroom.middleware', ['web', 'auth', Authorize::class]);
    }

    protected function defineRoutes($router): void
    {
        // Stand in for the host application's login route.
        $router->get('/login', fn (): string => 'login')->name('login');
    }

    #[Test]
    public function an_unauthenticated_visitor_is_sent_to_the_applications_login(): void
    {
        Gate::define('viewMailroom', fn (?User $user): bool => true);

        $this->get('/mailroom')->assertRedirect(route('login'));
    }

    #[Test]
    public function an_authenticated_and_authorized_user_reaches_the_mailbox(): void
    {
        Gate::define('viewMailroom', fn (?User $user): bool => true);

        $this->actingAs(new User)->get('/mailroom')->assertOk();
    }

    #[Test]
    public function authentication_alone_is_not_enough_when_the_gate_refuses(): void
    {
        Gate::define('viewMailroom', fn (?User $user): bool => false);

        $this->actingAs(new User)->get('/mailroom')->assertForbidden();
    }
}
