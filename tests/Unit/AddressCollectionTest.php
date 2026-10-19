<?php

namespace Ebbbang\Mailroom\Tests\Unit;

use Ebbbang\Mailroom\Models\AddressCollection;
use Ebbbang\Mailroom\Models\MailroomAddress;
use Ebbbang\Mailroom\Support\AddressRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AddressCollectionTest extends TestCase
{
    protected function address(AddressRole $role, string $address, ?string $name = null, int $position = 0): MailroomAddress
    {
        return (new MailroomAddress)->forceFill([
            'role' => $role,
            'position' => $position,
            'address' => $address,
            'name' => $name,
        ]);
    }

    protected function list(): AddressCollection
    {
        return new AddressCollection([
            $this->address(AddressRole::From, 'orders@acme.test', 'Acme Store'),
            $this->address(AddressRole::To, 'rachel@example.test', 'Rachel Okonkwo'),
            $this->address(AddressRole::To, 'sam@example.test', position: 1),
            $this->address(AddressRole::Cc, 'dara@example.test'),
        ]);
    }

    #[Test]
    public function display_writes_names_where_there_are_any(): void
    {
        $this->assertSame(
            'Rachel Okonkwo <rachel@example.test>, sam@example.test',
            $this->list()->ofRole(AddressRole::To)->display(),
        );
    }

    #[Test]
    public function an_empty_list_displays_as_an_empty_string(): void
    {
        $this->assertSame('', $this->list()->ofRole(AddressRole::Bcc)->display());
    }

    #[Test]
    public function of_role_accepts_several_roles_and_keeps_the_collection_type(): void
    {
        $recipients = $this->list()->ofRole(AddressRole::To, AddressRole::Cc);

        $this->assertInstanceOf(AddressCollection::class, $recipients);
        $this->assertSame(['rachel@example.test', 'sam@example.test', 'dara@example.test'], $recipients->emails());
    }

    #[Test]
    public function of_role_returns_roles_in_the_order_asked_for(): void
    {
        // Out of order on purpose: roles alphabetically, and To's positions reversed.
        $list = new AddressCollection([
            $this->address(AddressRole::Bcc, 'audit@example.test'),
            $this->address(AddressRole::Cc, 'dara@example.test'),
            $this->address(AddressRole::To, 'sam@example.test', position: 1),
            $this->address(AddressRole::To, 'rachel@example.test'),
        ]);

        $this->assertSame(
            ['rachel@example.test', 'sam@example.test', 'dara@example.test', 'audit@example.test'],
            $list->ofRole(AddressRole::To, AddressRole::Cc, AddressRole::Bcc)->emails(),
        );
    }

    #[Test]
    public function includes_ignores_case_and_surrounding_space(): void
    {
        $to = $this->list()->ofRole(AddressRole::To);

        $this->assertTrue($to->includes(' Rachel@Example.TEST '));
        $this->assertFalse($to->includes('dara@example.test'));
    }
}
