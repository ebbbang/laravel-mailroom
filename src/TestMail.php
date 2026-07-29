<?php

namespace Ebbbang\TestMail;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The package's public entry point, in the shape Telescope and Horizon use:
 * a small static surface the consuming application configures from a service
 * provider.
 */
class TestMail
{
    /**
     * The callback that decides who may open the mailbox.
     */
    public static ?Closure $authUsing = null;

    /**
     * Authorize mailbox access with a custom callback, overriding both the
     * gate and the local-environment fallback.
     *
     *     TestMail::auth(fn ($request) => $request->user()?->isAdmin());
     */
    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Decide whether this request may view the mailbox.
     *
     * Three escalating levers, in precedence order:
     *
     *   1. TestMail::auth()          -- arbitrary request-level logic
     *   2. Gate::define('viewTestMail') -- per-user rules, if defined
     *   3. local environment only    -- the default when neither is set
     *
     * Note that a gate defined as fn (?User $user) also runs for guests,
     * which is what lets this work without the "auth" middleware.
     */
    public static function check(Request $request): bool
    {
        if (static::$authUsing instanceof Closure) {
            return (bool) call_user_func(static::$authUsing, $request);
        }

        if (Gate::has('viewTestMail')) {
            return Gate::allows('viewTestMail');
        }

        return app()->environment('local');
    }

    public static function enabled(): bool
    {
        return (bool) config('test-mail.enabled');
    }

    /**
     * Forget any custom auth callback. Mainly useful between tests.
     */
    public static function flushState(): void
    {
        static::$authUsing = null;
    }
}
