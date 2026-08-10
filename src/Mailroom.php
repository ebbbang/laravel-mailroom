<?php

namespace Ebbbang\Mailroom;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The package's public entry point, in the shape Telescope and Horizon use:
 * a small static surface the consuming application configures from a service
 * provider.
 */
class Mailroom
{
    /**
     * The callback that decides who may open the mailbox.
     */
    public static ?Closure $authUsing = null;

    /**
     * Authorize mailbox access with a custom callback, overriding both the
     * gate and the local-environment fallback.
     *
     *     Mailroom::auth(fn ($request) => $request->user()?->isAdmin());
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
     *   1. Mailroom::auth()          -- arbitrary request-level logic
     *   2. Gate::define('viewMailroom') -- per-user rules, if defined
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

        if (Gate::has('viewMailroom')) {
            return Gate::allows('viewMailroom');
        }

        return app()->environment('local');
    }

    public static function enabled(): bool
    {
        return (bool) config('mailroom.enabled');
    }

    /**
     * Is there anywhere for a forwarded message to go?
     *
     * Without a configured mailer the feature is inert: the route is never
     * registered and the button never rendered, so nothing can leave the
     * mailbox until someone sets one up deliberately.
     */
    public static function canForward(): bool
    {
        return static::enabled() && filled(config('mailroom.forward.mailer'));
    }

    /**
     * May this request send a message on to a real inbox?
     *
     * Reading the mailbox and relaying from it are different privileges. A
     * consumer can open the mailbox up with a permissive auth callback -- an
     * IP check, say -- and that should not also hand out the ability to send
     * mail from their domain. So outside local development a forward needs a
     * genuine authenticated user, unless the consumer switches that off with
     * their eyes open.
     */
    public static function canForwardFrom(Request $request): bool
    {
        if (! static::canForward()) {
            return false;
        }

        if (! config('mailroom.forward.require_authenticated_user', true)) {
            return true;
        }

        if (app()->environment('local')) {
            return true;
        }

        return $request->user() !== null;
    }

    /**
     * Forget any custom auth callback. Mainly useful between tests.
     */
    public static function flushState(): void
    {
        static::$authUsing = null;
    }
}
