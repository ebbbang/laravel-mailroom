<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Events\MessageStored;
use Ebbbang\Mailroom\Models\MailroomAddress;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Every address on a captured message is a row in mailroom_addresses: one per
 * role and position, with the display name as it appeared on that message.
 */
class AddressStorageTest extends TestCase
{
    /**
     * @return array<int, array{0: string, 1: string|null}>
     */
    protected function pairs(MailroomMessage $message, AddressRole $role): array
    {
        return $message->addressesFor($role)
            ->map(fn (MailroomAddress $address): array => [$address->address, $address->name])
            ->all();
    }

    #[Test]
    public function every_address_is_stored_under_its_role_in_header_order(): void
    {
        Mail::to([
            ['email' => 'rachel@example.test', 'name' => 'Rachel Okonkwo'],
            ['email' => 'sam@example.test'],
        ])
            ->cc('dara@example.test')
            ->bcc('audit@example.test')
            ->send((new OrderShipped)->replyTo('support@example.test', 'Support'));

        $message = MailroomMessage::sole();

        $this->assertSame([['app@example.test', 'Example App']], $this->pairs($message, AddressRole::From));
        $this->assertSame(
            [['rachel@example.test', 'Rachel Okonkwo'], ['sam@example.test', null]],
            $this->pairs($message, AddressRole::To),
        );
        $this->assertSame([['dara@example.test', null]], $this->pairs($message, AddressRole::Cc));
        $this->assertSame([['audit@example.test', null]], $this->pairs($message, AddressRole::Bcc));
        $this->assertSame([['support@example.test', 'Support']], $this->pairs($message, AddressRole::ReplyTo));
        $this->assertSame([['app@example.test', null]], $this->pairs($message, AddressRole::EnvelopeFrom));

        // The envelope carries Bcc as well, which is how it differs from To.
        $this->assertSame(
            ['rachel@example.test', 'sam@example.test', 'dara@example.test', 'audit@example.test'],
            $message->addressesFor(AddressRole::EnvelopeTo)->emails(),
        );
    }

    #[Test]
    public function addresses_are_stored_lowercased_and_names_keep_their_case(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('Rachel@Example.TEST', 'Rachel Okonkwo')->subject('Case'));

        $this->assertSame(
            [['rachel@example.test', 'Rachel Okonkwo']],
            $this->pairs(MailroomMessage::sole(), AddressRole::To),
        );
    }

    /**
     * The column holds 255 characters, and MySQL in strict mode and
     * PostgreSQL refuse a longer value outright, which would fail the
     * application's send rather than just this row.
     */
    #[Test]
    public function a_name_longer_than_the_column_is_cut_to_fit(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test', str_repeat('Ü', 300))->subject('Long name'));

        $this->assertSame(
            str_repeat('Ü', 255),
            MailroomMessage::sole()->addressesFor(AddressRole::To)->first()?->name,
        );
    }

    #[Test]
    public function sender_and_envelope_sender_are_single_addresses(): void
    {
        $email = (new Email)
            ->from(new Address('orders@acme.test', 'Acme Store'))
            ->to('rachel@example.test')
            ->subject('Envelope')
            ->text('Hi.');

        resolve('mailer')->getSymfonyTransport()->send(
            $email,
            new Envelope(new Address('bounces@acme.test'), [new Address('rachel@example.test')]),
        );

        $message = MailroomMessage::sole();

        $this->assertSame('orders@acme.test', $message->sender()?->address);
        $this->assertSame('Acme Store', $message->sender()?->name);
        $this->assertSame('bounces@acme.test', $message->envelopeSender()?->address);
    }

    #[Test]
    public function a_message_with_no_cc_has_an_empty_list_rather_than_null(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test')->subject('No cc'));

        $this->assertTrue(MailroomMessage::sole()->addressesFor(AddressRole::Cc)->isEmpty());
    }

    #[Test]
    public function deleting_a_message_deletes_its_addresses(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test')->subject('One'));
        Mail::raw('Hi.', fn ($message) => $message->to('sam@example.test')->subject('Two'));

        $doomed = MailroomMessage::query()->where('subject', 'One')->sole();
        $doomed->delete();

        $this->assertSame(0, MailroomAddress::query()->where('mailroom_message_id', $doomed->id)->count());
        $this->assertGreaterThan(0, MailroomAddress::query()->count(), 'The other message kept its addresses.');
    }

    #[Test]
    public function pruning_deletes_addresses_along_with_the_message(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test')->subject('Old'));

        MailroomMessage::sole()->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

        $this->artisan('mailroom:prune', ['--days' => 7])->assertSuccessful();

        $this->assertSame(0, MailroomAddress::query()->count());
    }

    #[Test]
    public function listeners_to_message_stored_can_read_the_addresses(): void
    {
        $seen = null;

        Event::listen(function (MessageStored $event) use (&$seen): void {
            $seen = $event->message->addressesFor(AddressRole::To)->display();
        });

        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test', 'Rachel Okonkwo')->subject('Listened'));

        $this->assertSame('Rachel Okonkwo <rachel@example.test>', $seen);
    }
}
