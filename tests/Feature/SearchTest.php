<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Search runs against the subject, the text body and every address on the
 * message. The address half is what these pin down: it has to match what a
 * person would type, and nothing about how the addresses happen to be stored.
 */
class SearchTest extends TestCase
{
    protected function send(string $subject, string $to, ?string $name = null): void
    {
        Mail::raw('Body text.', function ($message) use ($subject, $to, $name): void {
            $message->to($to, $name)->subject($subject);
        });
    }

    /**
     * @return array<int, string>
     */
    protected function subjectsMatching(string $term): array
    {
        return MailroomMessage::query()->search($term)->orderBy('id')->pluck('subject')->all();
    }

    #[Test]
    public function a_percent_sign_is_searched_for_rather_than_matching_anything(): void
    {
        $this->send('50% off today', 'rachel@example.test');
        $this->send('500 off today', 'rachel@example.test');

        $this->assertSame(['50% off today'], $this->subjectsMatching('50%'));
    }

    #[Test]
    public function an_underscore_is_searched_for_rather_than_matching_any_character(): void
    {
        $this->send('Reset for user_id 7', 'rachel@example.test');
        $this->send('Reset for userid 7', 'rachel@example.test');

        $this->assertSame(['Reset for user_id 7'], $this->subjectsMatching('user_id'));
    }

    #[Test]
    public function characters_used_for_escaping_are_searched_for_literally(): void
    {
        $this->send('Saved to C:\reports', 'rachel@example.test');
        $this->send('Welcome aboard!', 'rachel@example.test');
        $this->send('Matched [draft]', 'rachel@example.test');

        $this->assertSame(['Saved to C:\reports'], $this->subjectsMatching('C:\rep'));
        $this->assertSame(['Welcome aboard!'], $this->subjectsMatching('aboard!'));
        $this->assertSame(['Matched [draft]'], $this->subjectsMatching('[draft]'));
    }

    /**
     * The suite only runs on SQLite, so the other drivers are checked by the
     * SQL they would be sent. Building it never opens a connection.
     */
    #[Test]
    #[DataProvider('drivers')]
    public function each_driver_gets_an_escape_it_understands(string $connection, bool $namesTheEscape, string $operator): void
    {
        // Through config rather than on(), which would switch the message
        // model and leave the address subquery on the default connection.
        config(['mailroom.database.connection' => $connection]);

        $query = MailroomMessage::query()->search('50%_off');
        $sql = $query->toSql();

        // Subject, body, address, name: every clause escapes the same way.
        $this->assertSame($namesTheEscape ? 4 : 0, substr_count($sql, "escape '\\'"));
        $this->assertSame(4, substr_count($sql, " {$operator} "));
        $this->assertSame(['%50\%\_off%', '%50\%\_off%'], array_slice($query->getBindings(), 0, 2));
    }

    /**
     * PostgreSQL's LIKE is case-sensitive, where the others are not, so it
     * gets ILIKE, or "rachel" would miss a name stored as "Rachel".
     *
     * @return array<string, array{string, bool, string}>
     */
    public static function drivers(): array
    {
        return [
            'sqlite' => ['testing', true, 'like'],
            'sqlsrv' => ['sqlsrv', true, 'like'],
            'mysql' => ['mysql', false, 'like'],
            'pgsql' => ['pgsql', false, 'ilike'],
        ];
    }

    #[Test]
    public function wildcards_in_an_address_are_searched_for_literally(): void
    {
        $this->send('For the underscore', 'first_last@example.test');
        $this->send('For the dot', 'first.last@example.test');

        $this->assertSame(['For the underscore'], $this->subjectsMatching('first_last'));
    }

    #[Test]
    public function it_finds_a_message_by_recipient_address(): void
    {
        $this->send('For Rachel', 'rachel@example.test');
        $this->send('For Sam', 'sam@example.test');

        $this->assertSame(['For Rachel'], $this->subjectsMatching('rachel@'));
    }

    #[Test]
    public function it_finds_a_message_by_display_name(): void
    {
        $this->send('For Rachel', 'rachel@example.test', 'Rachel Okonkwo');
        $this->send('For Sam', 'sam@example.test', 'Sam Ihejirika');

        $this->assertSame(['For Sam'], $this->subjectsMatching('Ihejirika'));
    }

    #[Test]
    public function reply_to_and_envelope_addresses_are_not_searched(): void
    {
        Mail::raw('Body text.', fn ($message) => $message
            ->to('rachel@example.test')
            ->replyTo('support@example.test')
            ->subject('With a reply-to'));

        $email = (new Email)
            ->from('app@example.test')
            ->to('sam@example.test')
            ->subject('Diverted')
            ->text('Body text.');

        resolve('mailer')->getSymfonyTransport()->send(
            $email,
            new Envelope(new Address('bounces@example.test'), [new Address('diverted@example.test')]),
        );

        $this->assertSame([], $this->subjectsMatching('support@'));
        $this->assertSame([], $this->subjectsMatching('bounces@'));
        $this->assertSame([], $this->subjectsMatching('diverted@'));
    }

    /**
     * JSON encoding stores "Müller" as "Müller", which a search for the
     * name as anyone would type it never matches.
     */
    #[Test]
    public function it_finds_a_display_name_with_non_ascii_characters(): void
    {
        $this->send('For Jürgen', 'juergen@example.test', 'Jürgen Müller');
        $this->send('For Sam', 'sam@example.test', 'Sam Ihejirika');

        $this->assertSame(['For Jürgen'], $this->subjectsMatching('Müller'));
    }

    /**
     * Encoded as JSON, every message carries the key names "address" and
     * "name", so a search over that text would match the whole mailbox.
     */
    #[Test]
    public function the_storage_format_is_not_searchable(): void
    {
        $this->send('First', 'rachel@example.test', 'Rachel Okonkwo');
        $this->send('Second', 'sam@example.test', 'Sam Ihejirika');

        $this->assertSame([], $this->subjectsMatching('address'));
        $this->assertSame([], $this->subjectsMatching('name'));
    }
}
