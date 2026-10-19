# Access control

Mailroom ships **no login page**. Access is decided in three escalating steps.

**1. Nothing configured.** The mailbox is reachable in the `local` environment only.

**2. A gate.** Define `viewMailroom` and it takes over completely:

```php
Gate::define('viewMailroom', fn (?User $user) => $user?->isAdmin());
```

Type the parameter as nullable and guests are evaluated too, which is what lets this work without auth middleware.

**3. A callback**, for anything a gate cannot express:

```php
Mailroom::auth(fn (Request $request) => $request->ip() === '10.0.0.1');
```

To put the mailbox behind your application's existing login, add `auth` to the middleware stack and your own login flow handles the redirect:

```php
'middleware' => ['web', 'auth', Authorize::class],
```

Forwarding and the spam check are separate privileges with their own rules: see [Forwarding a message](./forwarding.md#keeping-it-safe) and [Spam check](./spam-check.md#what-leaves-your-application).

## Rendering untrusted mail safely

Email HTML is attacker-controlled as far as your app is concerned. Message bodies are served from their own route into an `<iframe sandbox>` with neither `allow-scripts` nor `allow-same-origin`, putting them in an opaque origin that cannot run scripts, reach the parent page, or touch cookies, under a `default-src 'none'` CSP.
