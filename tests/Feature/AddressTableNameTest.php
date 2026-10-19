<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The table name is config, set before the migrations run, so this class
 * sets it in the environment rather than inside a test.
 */
class AddressTableNameTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mailroom.database.addresses_table', 'captured_addresses');
    }

    #[Test]
    public function addresses_are_written_to_the_configured_table(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test')->subject('Renamed'));

        $this->assertFalse(Schema::hasTable('mailroom_addresses'));
        $this->assertGreaterThan(0, DB::table('captured_addresses')->count());
        $this->assertSame(
            ['rachel@example.test'],
            MailroomMessage::sole()->addressesFor(AddressRole::To)->emails(),
        );
    }
}
