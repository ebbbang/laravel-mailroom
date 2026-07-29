<?php

namespace Ebbbang\TestMail\Tests\Feature;

use Ebbbang\TestMail\TestMail;
use Ebbbang\TestMail\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;

class RouteAccessTest extends TestCase
{
    #[Test]
    public function it_denies_access_outside_local_when_no_gate_is_defined(): void
    {
        // The suite runs with APP_ENV=testing, so the local-only fallback
        // must refuse rather than fall open.
        $this->get('/test-mail')->assertForbidden();
    }

    #[Test]
    public function it_allows_access_in_the_local_environment_by_default(): void
    {
        $this->app['env'] = 'local';

        $this->get('/test-mail')->assertOk();
    }

    #[Test]
    public function a_defined_gate_takes_over_from_the_environment_fallback(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => $user?->email === 'allowed@example.test');

        $this->actingAs(tap(new User)->forceFill(['email' => 'denied@example.test']))
            ->get('/test-mail')
            ->assertForbidden();

        $this->actingAs(tap(new User)->forceFill(['email' => 'allowed@example.test']))
            ->get('/test-mail')
            ->assertOk();
    }

    #[Test]
    public function a_gate_can_admit_guests_which_is_what_lets_this_work_without_auth_middleware(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => true);

        $this->get('/test-mail')->assertOk();
    }

    #[Test]
    public function the_auth_callback_overrides_both_the_gate_and_the_environment(): void
    {
        Gate::define('viewTestMail', fn (?User $user): bool => true);

        TestMail::auth(fn ($request): bool => $request->query('key') === 'let-me-in');

        $this->get('/test-mail')->assertForbidden();
        $this->get('/test-mail?key=let-me-in')->assertOk();
    }
}
