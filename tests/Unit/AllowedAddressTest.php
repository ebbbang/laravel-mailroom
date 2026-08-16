<?php

namespace Ebbbang\Mailroom\Tests\Unit;

use Ebbbang\Mailroom\Forwarding\AllowedAddress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The allowlist is the difference between a bounded relay and an open one, so
 * the matching is tested on its own, without a request in the way.
 */
class AllowedAddressTest extends TestCase
{
    #[Test]
    public function an_empty_list_permits_anything(): void
    {
        // The shipped default. Every existing install must keep behaving
        // exactly as it did before this release.
        $rule = new AllowedAddress([]);

        $this->assertTrue($rule->permits('anyone@anywhere.test'));
    }

    #[Test]
    public function a_whole_address_must_match_in_full(): void
    {
        $rule = new AllowedAddress(['qa@yourco.com']);

        $this->assertTrue($rule->permits('qa@yourco.com'));
        $this->assertFalse($rule->permits('someone@yourco.com'));
        $this->assertFalse($rule->permits('qa@elsewhere.com'));
    }

    #[Test]
    public function a_domain_entry_permits_everyone_in_it(): void
    {
        $rule = new AllowedAddress(['@yourco.com']);

        $this->assertTrue($rule->permits('qa@yourco.com'));
        $this->assertTrue($rule->permits('someone.else@yourco.com'));
    }

    #[Test]
    public function a_domain_entry_does_not_match_a_domain_that_merely_ends_with_it(): void
    {
        /*
         * The "@" has to travel with the entry. Compare on "yourco.com" alone
         * and an attacker registering "notyourco.com" or a subdomain gets a
         * pass; with the "@" attached, neither ends with it.
         */
        $rule = new AllowedAddress(['@yourco.com']);

        $this->assertFalse($rule->permits('someone@notyourco.com'));
        $this->assertFalse($rule->permits('someone@evil-yourco.com'));
        $this->assertFalse($rule->permits('someone@sub.yourco.com'));
    }

    #[Test]
    public function matching_ignores_case_and_surrounding_space(): void
    {
        $rule = new AllowedAddress([' @YourCo.com ', 'QA@Partner.test']);

        $this->assertTrue($rule->permits('Someone@YOURCO.COM'));
        $this->assertTrue($rule->permits('  qa@partner.test  '));
    }

    #[Test]
    public function several_entries_are_all_considered(): void
    {
        $rule = new AllowedAddress(['@yourco.com', 'contractor@partner.test']);

        $this->assertTrue($rule->permits('dev@yourco.com'));
        $this->assertTrue($rule->permits('contractor@partner.test'));
        $this->assertFalse($rule->permits('someone@partner.test'));
    }

    #[Test]
    public function blank_entries_are_ignored_rather_than_matching_everything(): void
    {
        // A trailing comma in MAILROOM_FORWARD_ALLOWED produces an empty
        // entry. It must not become a wildcard.
        $rule = new AllowedAddress(['@yourco.com', '', '   ']);

        $this->assertTrue($rule->permits('qa@yourco.com'));
        $this->assertFalse($rule->permits('qa@elsewhere.com'));
    }

    #[Test]
    public function an_address_with_no_at_sign_is_refused_by_a_domain_entry(): void
    {
        $rule = new AllowedAddress(['@yourco.com']);

        $this->assertFalse($rule->permits('not-an-address'));
    }

    #[Test]
    public function the_failure_message_names_what_is_permitted(): void
    {
        $rule = new AllowedAddress(['@yourco.com']);
        $message = null;

        $rule->validate('to', 'someone@elsewhere.test', function (string $error) use (&$message): void {
            $message = $error;
        });

        $this->assertNotNull($message);
        $this->assertStringContainsString('@yourco.com', $message);
    }

    #[Test]
    public function a_permitted_address_raises_no_failure(): void
    {
        $rule = new AllowedAddress(['@yourco.com']);
        $failed = false;

        $rule->validate('to', 'qa@yourco.com', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
