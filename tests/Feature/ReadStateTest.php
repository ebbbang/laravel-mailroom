<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Read state belongs to a person, so every test here turns on who is signed in.
 *
 * The readers are unsaved models deliberately. All the package ever asks of the
 * guard is `getAuthIdentifier()`, and keeping the tests to that pins how little
 * it needs to know about the application's users table.
 */
class ReadStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    protected function reader(int|string $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    protected function send(string $order = 'A-1001'): MailroomMessage
    {
        Mail::to('rachel@example.test')->send(new OrderShipped($order));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    /**
     * Just the message list.
     *
     * The stylesheet is inlined into every page, so searching a whole document
     * for a class name finds the CSS rule whether or not a single row carries
     * it. Everything asserted below is markup, scoped to where it would appear.
     */
    protected function listMarkup(string $html): string
    {
        $start = strpos($html, '<nav class="mr-list"');

        $this->assertNotFalse($start, 'The page rendered no message list.');

        return substr($html, $start, (int) strpos($html, '</nav>', $start) - $start);
    }

    #[Test]
    public function signed_out_there_is_no_read_state_at_all(): void
    {
        $message = $this->send();

        $html = $this->get('/mailroom/'.$message->id)->assertOk()->getContent();
        $list = $this->listMarkup($html);

        $this->assertSame(0, MailroomRead::query()->count(), 'Opening a message marked it read with nobody signed in.');
        $this->assertStringNotContainsString('aria-label="Unread"', $list);
        $this->assertStringNotContainsString('mr-item-read', $list);
        $this->assertStringNotContainsString('Mark page read', $list);
        $this->assertStringNotContainsString('Mark unread', $html);
        $this->assertStringNotContainsString('"mr-count mr-count-unread"', $html);
    }

    #[Test]
    public function the_read_routes_refuse_without_a_signed_in_reader(): void
    {
        $message = $this->send();

        $this->post('/mailroom/'.$message->id.'/read')->assertForbidden();
        $this->delete('/mailroom/'.$message->id.'/read')->assertForbidden();
        $this->post('/mailroom/read', ['ids' => [$message->id]])->assertForbidden();
        $this->delete('/mailroom/read', ['ids' => [$message->id]])->assertForbidden();

        $this->assertSame(0, MailroomRead::query()->count());
    }

    #[Test]
    public function opening_a_message_marks_it_read_and_polling_never_does(): void
    {
        $message = $this->send();

        $html = $this->actingAs($this->reader(7))
            ->get('/mailroom/'.$message->id)
            ->assertOk()
            ->getContent();

        $read = MailroomRead::query()->sole();

        $this->assertSame($message->id, (int) $read->mailroom_message_id);
        $this->assertSame('7', $read->reader_id);
        $this->assertNotNull($read->read_at);

        // The row the reader just clicked shows its new state straight away,
        // rather than staying bold until the next page load.
        $this->assertStringContainsString('class="mr-item mr-item-read"', $this->listMarkup($html));
        $this->assertStringContainsString('Mark unread', $html);

        // The mailbox polls in the background for new mail, so that endpoint
        // must never be able to mark anything.
        $this->send('A-2');

        $this->actingAs($this->reader(7))->get('/mailroom/recent')->assertOk();

        $this->assertSame(1, MailroomRead::query()->count());
    }

    #[Test]
    public function read_state_is_per_person(): void
    {
        $message = $this->send();

        $this->actingAs($this->reader(7))->get('/mailroom/'.$message->id)->assertOk();

        $mine = $this->listMarkup($this->actingAs($this->reader(7))->get('/mailroom')->assertOk()->getContent());
        $theirs = $this->listMarkup($this->actingAs($this->reader(9))->get('/mailroom')->assertOk()->getContent());

        $this->assertStringNotContainsString('aria-label="Unread"', $mine, 'A message this reader opened still showed as unread.');
        $this->assertStringContainsString('aria-label="Unread"', $theirs, 'One reader opening a message marked it read for everybody.');
    }

    #[Test]
    public function marking_unread_clears_the_row_and_lands_back_on_the_list(): void
    {
        $message = $this->send();
        $reader = $this->reader(7);

        $this->actingAs($reader)->get('/mailroom/'.$message->id)->assertOk();

        $this->assertSame(1, MailroomRead::query()->count());

        // The list, not the message: returning to the message would mark it
        // read again on arrival. The list's place comes along with it.
        $this->actingAs($reader)
            ->delete('/mailroom/'.$message->id.'/read', ['search' => 'rachel', 'page' => '2'])
            ->assertRedirect(route('mailroom.index', ['search' => 'rachel', 'page' => '2']));

        $this->assertSame(0, MailroomRead::query()->count());
    }

    #[Test]
    public function a_page_can_be_marked_read_and_unread_at_once(): void
    {
        $first = $this->send('A-1');
        $second = $this->send('A-2');
        $third = $this->send('A-3');
        $reader = $this->reader(7);

        $this->actingAs($reader)
            ->post('/mailroom/read', ['ids' => [$first->id, $second->id]])
            ->assertRedirect(route('mailroom.index'));

        // Only what was submitted, so marking page one leaves page two alone.
        $this->assertSame(
            [$first->id, $second->id],
            MailroomRead::query()
                ->orderBy('mailroom_message_id')
                ->pluck('mailroom_message_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );

        $this->actingAs($reader)
            ->delete('/mailroom/read', ['ids' => [$first->id, $second->id, $third->id]])
            ->assertRedirect(route('mailroom.index'));

        $this->assertSame(0, MailroomRead::query()->count());
    }

    #[Test]
    public function the_page_wide_routes_need_ids(): void
    {
        $this->actingAs($this->reader(7))
            ->post('/mailroom/read', [])
            ->assertSessionHasErrors('ids');
    }

    #[Test]
    public function the_unread_count_follows_the_filters(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped('A-1'));
        Mail::to('rachel@example.test')->send(new OrderShipped('A-2'));
        Mail::to('sam@example.test')->send(new OrderShipped('B-1'));

        $reader = $this->reader(7);

        $this->assertStringContainsString(
            '3 unread',
            $this->actingAs($reader)->get('/mailroom')->assertOk()->getContent()
        );

        // Counted on the same filtered query as the list, so a count of
        // everything cannot contradict what is on screen.
        $this->assertStringContainsString(
            '1 unread',
            $this->actingAs($reader)->get('/mailroom?search=sam@example.test')->assertOk()->getContent()
        );
    }

    #[Test]
    public function deleting_a_message_removes_its_reads_without_relying_on_the_foreign_key(): void
    {
        $message = $this->send();

        $this->actingAs($this->reader(7))->get('/mailroom/'.$message->id)->assertOk();

        // The cascade would empty the table regardless, and SQLite cannot turn
        // foreign keys off inside the transaction each test runs in. So this
        // watches for the delete the model issues itself.
        DB::enableQueryLog();

        $message->delete();

        $table = (new MailroomRead)->getTable();

        $this->assertTrue(
            collect(DB::getQueryLog())->contains(
                fn (array $entry): bool => str_starts_with($entry['query'], 'delete from "'.$table.'"')
            ),
            'Deleting a message left its reads to the foreign key cascade.'
        );

        $this->assertSame(0, MailroomRead::query()->count());
    }
}
