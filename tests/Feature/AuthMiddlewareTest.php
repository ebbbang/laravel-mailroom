<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\Http\Middleware\Authorize;
use Ebbbang\TestMail\Tests\TestCase;
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

        $app['config']->set('test-mail.middleware', ['web', 'auth', Authorize::class]);
    }

    protected function defineRoutes($router): void
    {
        // Stand in for the host application's login route.
        $router->get('/login', fn (): string => 'login')->name('login');
    }

    #[Test]
    public function an_unauthenticated_visitor_is_sent_to_the_applications_login(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => true);

        $this->get('/test-mail')->assertRedirect(route('login'));
    }

    #[Test]
    public function an_authenticated_and_authorized_user_reaches_the_mailbox(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => true);

        $this->actingAs(new User)->get('/test-mail')->assertOk();
    }

    #[Test]
    public function authentication_alone_is_not_enough_when_the_gate_refuses(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => false);

        $this->actingAs(new User)->get('/test-mail')->assertForbidden();
    }
}
