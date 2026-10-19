<?php

namespace Ebbbang\Mailroom\Support;

/**
 * One address as the People sidebar sees it: how many messages it is on,
 * and in which roles.
 */
final readonly class Person
{
    /**
     * @param  array<string, int>  $roles  AddressRole value => rows in that role, zeros dropped
     */
    public function __construct(
        public string $address,
        public ?string $name,
        public int $total,
        public array $roles,
    ) {}

    /**
     * The second line under the address: "Rachel Okonkwo · received 31, cc 3".
     *
     * From reads as "sent" and To as "received" because that is what they
     * mean to someone scanning a list of people; Cc and Bcc keep their own
     * names, since "received" would hide that the person was only copied.
     */
    public function summary(): string
    {
        $roles = collect($this->roles)
            ->map(fn (int $count, string $role): string => match (AddressRole::from($role)) {
                AddressRole::From => 'sent',
                AddressRole::To => 'received',
                default => $role,
            }.' '.$count)
            ->implode(', ');

        return filled($this->name) ? $this->name.' · '.$roles : $roles;
    }
}
