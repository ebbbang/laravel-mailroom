<?php

namespace Ebbbang\Mailroom\Recording;

use Ebbbang\Mailroom\Support\AddressRole;

final readonly class AddressPayload
{
    /**
     * The name column's width. A display name is whatever the application
     * wrote, and MySQL in strict mode and PostgreSQL reject a longer value
     * rather than truncating it, which would fail the send itself. The cut
     * only reaches the table: the stored headers keep the name in full.
     *
     * Addresses are not cut. SMTP limits a path to 256 octets, brackets
     * included, so any address a relay would accept already fits.
     */
    public const NAME_LENGTH = 255;

    public function __construct(
        public AddressRole $role,
        public int $position,
        public string $address,
        public ?string $name,
    ) {}

    /**
     * @return array{role: string, position: int, address: string, name: string|null}
     */
    public function toRow(): array
    {
        return [
            'role' => $this->role->value,
            'position' => $this->position,
            'address' => mb_strtolower($this->address),
            'name' => $this->name === null ? null : mb_substr($this->name, 0, self::NAME_LENGTH),
        ];
    }
}
