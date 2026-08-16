<?php

namespace Ebbbang\Mailroom\Forwarding;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Bound where a captured message may be forwarded.
 *
 * On a shared staging box, anyone who reaches the mailbox can send stored mail
 * -- real contents, password reset links and all -- to any address they type.
 * An allowlist turns that from an open relay into a bounded one.
 *
 * Enforced as a validation rule rather than inside MessageForwarder so a
 * refusal lands beside the field that caused it, with the rejected address
 * still there to correct.
 */
class AllowedAddress implements ValidationRule
{
    /**
     * @param  array<int, string>  $allowed  Whole addresses, or "@domain" for
     *                                       everyone in one. Empty permits any.
     */
    public function __construct(protected array $allowed) {}

    public static function fromConfig(): self
    {
        return new self((array) config('mailroom.forward.allowed', []));
    }

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->permits((string) $value)) {
            return;
        }

        $fail(sprintf(
            'Forwarding to this address is not allowed. Permitted destinations: %s.',
            implode(', ', $this->allowed)
        ));
    }

    /**
     * Would this address be accepted?
     */
    public function permits(string $address): bool
    {
        if ($this->allowed === []) {
            return true;
        }

        $address = mb_strtolower(trim($address));

        foreach ($this->allowed as $entry) {
            $entry = mb_strtolower(trim((string) $entry));

            if ($entry === '') {
                continue;
            }

            /*
             * Carrying the "@" into the comparison is what keeps a domain
             * entry honest: "@yourco.com" refuses "someone@notyourco.com" and
             * "someone@sub.yourco.com", because neither ends with the "@". An
             * entry written without the "@" is treated as a whole address
             * below, so there is no form of this that degrades into matching a
             * bare substring.
             */
            if (str_starts_with($entry, '@')) {
                if (str_ends_with($address, $entry)) {
                    return true;
                }

                continue;
            }

            if ($entry === $address) {
                return true;
            }
        }

        return false;
    }
}
