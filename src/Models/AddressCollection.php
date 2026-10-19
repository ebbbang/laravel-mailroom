<?php

namespace Ebbbang\Mailroom\Models;

use Ebbbang\Mailroom\Support\AddressRole;
use Illuminate\Database\Eloquent\Collection;

/**
 * The addresses of a message, or some of them.
 *
 * @extends Collection<int, MailroomAddress>
 */
class AddressCollection extends Collection
{
    /**
     * The addresses in the given roles, in the order the roles are given and
     * header order within each: ofRole(To, Cc) is every To, then every Cc.
     */
    public function ofRole(AddressRole ...$roles): static
    {
        return $this
            ->filter(fn (MailroomAddress $address): bool => in_array($address->role, $roles, true))
            ->sortBy([
                fn (MailroomAddress $a, MailroomAddress $b): int => array_search($a->role, $roles, true) <=> array_search($b->role, $roles, true),
                fn (MailroomAddress $a, MailroomAddress $b): int => $a->position <=> $b->position,
            ])
            ->values();
    }

    /**
     * The list as a person would write it: "Rachel Okonkwo <rachel@…>, sam@…".
     */
    public function display(): string
    {
        return $this->map(fn (MailroomAddress $address): string => $address->display())->implode(', ');
    }

    /**
     * @return array<int, string>
     */
    public function emails(): array
    {
        return $this->map(fn (MailroomAddress $address): string => $address->address)->values()->all();
    }

    /**
     * Stored addresses are already lowercased, so only the candidate needs
     * folding.
     */
    public function includes(string $address): bool
    {
        return in_array(mb_strtolower(trim($address)), $this->emails(), true);
    }
}
