<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * The addresses migration moves live data, so both directions are pinned
 * against rows in the JSON schema it migrates from.
 */
class AddressMigrationTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $legacy = [
        'from' => [['address' => 'orders@acme.test', 'name' => 'Acme Store']],
        'to' => [
            ['address' => 'rachel@example.test', 'name' => 'Rachel Okonkwo'],
            ['address' => 'juergen@example.test', 'name' => 'Jürgen Müller'],
        ],
        'cc' => [['address' => 'dara@example.test', 'name' => null]],
        'bcc' => [],
        'reply_to' => [['address' => 'support@acme.test', 'name' => null]],
        'envelope_recipients' => [
            ['address' => 'rachel@example.test', 'name' => null],
            ['address' => 'juergen@example.test', 'name' => null],
            ['address' => 'dara@example.test', 'name' => null],
        ],
        'envelope_sender' => 'bounces@acme.test',
    ];

    protected function rollBackToTheJsonSchema(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    }

    protected function insertLegacyMessage(): int
    {
        return DB::table('mailroom_messages')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'mailer' => 'mailroom',
            'subject' => 'Written before the addresses table',
            'from' => json_encode($this->legacy['from']),
            'to' => json_encode($this->legacy['to']),
            'cc' => json_encode($this->legacy['cc']),
            'bcc' => json_encode($this->legacy['bcc']),
            'reply_to' => json_encode($this->legacy['reply_to']),
            'envelope_recipients' => json_encode($this->legacy['envelope_recipients']),
            'envelope_sender' => $this->legacy['envelope_sender'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function migrating_moves_every_address_into_rows_in_order(): void
    {
        $this->rollBackToTheJsonSchema();
        $id = $this->insertLegacyMessage();

        $this->artisan('migrate')->assertSuccessful();

        $message = MailroomMessage::query()->findOrFail($id);

        $this->assertSame('Acme Store <orders@acme.test>', $message->addressesFor(AddressRole::From)->display());
        $this->assertSame(
            'Rachel Okonkwo <rachel@example.test>, Jürgen Müller <juergen@example.test>',
            $message->addressesFor(AddressRole::To)->display(),
        );
        $this->assertSame(['dara@example.test'], $message->addressesFor(AddressRole::Cc)->emails());
        $this->assertSame(['support@acme.test'], $message->addressesFor(AddressRole::ReplyTo)->emails());
        $this->assertSame(
            ['rachel@example.test', 'juergen@example.test', 'dara@example.test'],
            $message->addressesFor(AddressRole::EnvelopeTo)->emails(),
        );
        $this->assertSame('bounces@acme.test', $message->envelopeSender()?->address);
    }

    #[Test]
    public function migrating_drops_the_old_columns(): void
    {
        $this->rollBackToTheJsonSchema();
        $this->artisan('migrate')->assertSuccessful();

        foreach (['from', 'to', 'cc', 'bcc', 'reply_to', 'envelope_recipients', 'envelope_sender'] as $column) {
            $this->assertFalse(Schema::hasColumn('mailroom_messages', $column), "{$column} is still there.");
        }
    }

    #[Test]
    public function migrating_lowercases_addresses(): void
    {
        $this->rollBackToTheJsonSchema();

        $id = $this->insertLegacyMessage();
        DB::table('mailroom_messages')->where('id', $id)->update([
            'to' => json_encode([['address' => 'Rachel@Example.TEST', 'name' => null]]),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(
            ['rachel@example.test'],
            MailroomMessage::query()->findOrFail($id)->addressesFor(AddressRole::To)->emails(),
        );
    }

    /**
     * SMTP's envelope carries no names, and messages captured after the
     * migration store none there, so older ones must not show one either.
     */
    #[Test]
    public function migrating_leaves_envelope_recipients_without_names(): void
    {
        $this->rollBackToTheJsonSchema();

        $id = $this->insertLegacyMessage();
        DB::table('mailroom_messages')->where('id', $id)->update([
            'envelope_recipients' => json_encode([['address' => 'rachel@example.test', 'name' => 'Rachel Okonkwo']]),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertNull(MailroomMessage::query()->findOrFail($id)->addressesFor(AddressRole::EnvelopeTo)->first()?->name);
    }

    #[Test]
    public function migrating_cuts_a_name_longer_than_the_column(): void
    {
        $this->rollBackToTheJsonSchema();

        $id = $this->insertLegacyMessage();
        DB::table('mailroom_messages')->where('id', $id)->update([
            'to' => json_encode([['address' => 'rachel@example.test', 'name' => str_repeat('Ü', 300)]]),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(
            str_repeat('Ü', 255),
            MailroomMessage::query()->findOrFail($id)->addressesFor(AddressRole::To)->first()?->name,
        );
    }

    #[Test]
    public function rolling_back_restores_the_json_exactly(): void
    {
        $this->rollBackToTheJsonSchema();
        $id = $this->insertLegacyMessage();
        $this->artisan('migrate')->assertSuccessful();

        $this->rollBackToTheJsonSchema();

        $row = DB::table('mailroom_messages')->where('id', $id)->first();

        foreach (['from', 'to', 'cc', 'bcc', 'reply_to', 'envelope_recipients'] as $column) {
            $this->assertSame($this->legacy[$column], json_decode($row->{$column}, true), "{$column} came back different.");
        }

        $this->assertSame($this->legacy['envelope_sender'], $row->envelope_sender);
        $this->assertFalse(Schema::hasTable('mailroom_addresses'));
    }
}
