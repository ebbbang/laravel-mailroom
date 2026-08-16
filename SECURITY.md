# Security Policy

## Supported versions

While the package is below 1.0, only the latest minor receives fixes.

| Version | Supported |
|---------|-----------|
| 0.5.x   | yes       |
| 0.4.x   | no        |
| 0.3.x   | no        |
| 0.2.x   | no        |
| 0.1.x   | no        |

## Reporting a vulnerability

Please **do not open a public issue.** Report privately to
**ebb.bang@gmail.com**, or use GitHub's
[private vulnerability reporting](https://github.com/ebbbang/laravel-mailroom/security/advisories/new).

Include the package and Laravel versions, what an attacker can achieve, and
the smallest reproduction you have. Expect an acknowledgement within a few
days.

## What this package's threat model actually is

Worth reading before reporting, because two things here look alarming and are
deliberate.

**The mailbox renders attacker-controlled content.** Anything your application
mails can end up displayed in `/mailroom`, and a message body or attachment
may contain hostile HTML, SVG or JavaScript. That is the central risk, and the
mitigations are:

- Message bodies and HTML attachments are shown inside an `<iframe sandbox>`
  with neither `allow-scripts` nor `allow-same-origin`, so they sit in an
  opaque origin that cannot script, reach the parent page, or read cookies —
  under a `default-src 'none'` policy.
- Attachment previews serve a content type from an internal allowlist, never
  the attachment's declared `mime_type`, which is untrusted input. A type
  outside the allowlist gets no preview at all.
- Anything text-shaped — `.html`, `.js`, `.svg` source, `.csv` — is read and
  escaped server-side rather than served, so it has no content type to be
  reinterpreted through.
- Attachment downloads are always `application/octet-stream` with
  `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.

**PDF previews are deliberately exempt from the `sandbox` policy.** Chrome
loads its PDF viewer as an extension in a cross-process iframe, and a sandbox
directive on the response blocks that extension's own scripts, so the PDF does
not render at all ([crbug.com/413851](https://bugs.chromium.org/p/chromium/issues/detail?id=413851)).
A page-level CSP never governed PDF JavaScript in the first place — that runs
inside PDFium's sandbox, where it cannot reach the page's DOM, cookies or
storage. The allowlisted content type, `nosniff` and
`Cross-Origin-Resource-Policy` still apply. This is a documented trade-off
rather than an oversight.

**Forwarding makes the mailbox a sending surface.** Once
`MAILROOM_FORWARD_MAILER` is set, anyone who can both reach the mailbox and
satisfy the forwarding guard can send stored mail — from your domain, through
your relay — to an address of their choosing. Four things bound that:

- With no mailer configured the route is never registered, so the default
  install has no such surface at all.
- Outside `local`, a forward requires an authenticated user. Reading and
  relaying are deliberately separate privileges: a permissive
  `Mailroom::auth()` callback grants the first, not the second. Setting
  `forward.require_authenticated_user` to `false` accepts that anyone who can
  open the mailbox can send through it.
- Only the submitted address receives the copy. The envelope is rebuilt with
  that single recipient, so `Cc` and `Bcc` recipients of the original are not
  reached even on the byte-identical path.
- A mailer whose transport is itself `mailroom` is refused, so a forward
  cannot loop.
- `MAILROOM_FORWARD_ALLOWED` bounds the destinations to named addresses or
  whole domains. Left empty, any address is permitted.
- `MAILROOM_FORWARD_RATE_LIMIT` caps forwards a minute, counted per signed-in
  user and otherwise per IP, so a compromised session cannot drain a relay.

The forwarded copy is the stored MIME, so it carries whatever the original
did. Treat the destination as you would the mailbox itself: it receives real
message contents, password reset links included.

## Two things that are your responsibility

**Access control.** With nothing configured the mailbox is reachable in the
`local` environment only. Anywhere else you must define a `viewMailroom` gate
or a `Mailroom::auth()` callback, and add `auth` to the middleware stack if you
want it behind a login. A mailbox left open is a mailbox anyone can read.

**Production.** The package is disabled unless `MAILROOM_ENABLED=true`. If you
opt in on a production system, you are storing the full contents of every
outgoing email — including password reset links and anything else sensitive —
in your database and on a disk. Set a short `prune.retention_days` and keep the
gate tight.
