<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Ebbbang\Mailroom\Support\Mailbox;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * The sidebar's mailboxes and people, as the controller builds them. What
 * they look like is pinned in the view tests; this is what they contain.
 */
class MailboxFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    protected function reader(int $id = 7): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    protected function send(string $subject, string $to = 'rachel@example.test', array $cc = [], array $bcc = []): MailroomMessage
    {
        Mail::raw('Body.', function ($message) use ($subject, $to, $cc, $bcc): void {
            $message->to($to)->subject($subject);

            foreach ($cc as $address) {
                $message->cc($address);
            }

            foreach ($bcc as $address) {
                $message->bcc($address);
            }
        });

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    protected function score(MailroomMessage $message, float $score): void
    {
        $message->forceFill(['spam_score' => $score, 'spam_rules' => [], 'spam_checked_at' => now()])->save();
    }

    /**
     * @return array<int, string>
     */
    protected function listed(TestResponse $response): array
    {
        return $response->assertOk()->viewData('messages')->pluck('subject')->all();
    }

    #[Test]
    public function the_inbox_lists_everything_newest_first(): void
    {
        $this->send('First');
        $this->send('Second');

        $this->assertSame(['Second', 'First'], $this->listed($this->get('/mailroom')));
    }

    #[Test]
    public function unread_lists_only_what_the_signed_in_reader_has_not_opened(): void
    {
        $read = $this->send('Read');
        $this->send('Unread');

        MailroomRead::markRead([$read->id], '7');

        $this->assertSame(['Unread'], $this->listed($this->actingAs($this->reader())->get('/mailroom?box=unread')));
    }

    #[Test]
    public function unread_with_nobody_signed_in_falls_back_to_the_inbox(): void
    {
        $this->send('First');
        $this->send('Second');

        $response = $this->get('/mailroom?box=unread');

        $this->assertSame(['Second', 'First'], $this->listed($response));
        $this->assertSame(Mailbox::Inbox, $response->viewData('box'));
    }

    #[Test]
    public function an_unknown_box_is_the_inbox(): void
    {
        $this->send('Only');

        $response = $this->get('/mailroom?box=nonsense');

        $this->assertSame(['Only'], $this->listed($response));
        $this->assertSame(Mailbox::Inbox, $response->viewData('box'));
    }

    #[Test]
    public function spam_checked_lists_checked_messages_highest_score_first(): void
    {
        $this->send('Never checked');
        $this->score($this->send('Clean'), 0.3);
        $this->score($this->send('High'), 6.2);
        $this->score($this->send('Borderline, older'), 2.4);
        $this->score($this->send('Borderline, newer'), 2.4);

        $this->assertSame(
            ['High', 'Borderline, newer', 'Borderline, older', 'Clean'],
            $this->listed($this->get('/mailroom?box=spam-checked')),
        );
    }

    #[Test]
    public function a_person_matches_every_message_they_appear_on_in_any_role(): void
    {
        $this->send('To Kit', 'kit@example.test');
        $this->send('Kit copied', 'rachel@example.test', cc: ['kit@example.test']);
        $this->send('Kit blind copied', 'rachel@example.test', bcc: ['kit@example.test']);
        $this->send('Not Kit', 'rachel@example.test');

        $this->assertSame(
            ['Kit blind copied', 'Kit copied', 'To Kit'],
            $this->listed($this->get('/mailroom?person=Kit@Example.test')),
        );
    }

    #[Test]
    public function a_person_matches_what_they_sent(): void
    {
        $this->send('From the app');
        Mail::raw('Hi.', fn ($message) => $message->from('orders@acme.test')->to('rachel@example.test')->subject('From orders'));

        $this->assertSame(['From orders'], $this->listed($this->get('/mailroom?person=orders@acme.test')));
    }

    #[Test]
    public function mailbox_counts_cover_the_whole_mailbox_whatever_is_filtered(): void
    {
        $read = $this->send('Read', 'kit@example.test');
        $this->score($this->send('Checked'), 3.0);
        $this->send('Plain');

        MailroomRead::markRead([$read->id], '7');

        $response = $this->actingAs($this->reader())->get('/mailroom?person=kit@example.test&search=Read');

        $this->assertSame(['inbox' => 3, 'unread' => 2, 'spam-checked' => 1], $response->viewData('boxCounts'));
    }

    #[Test]
    public function there_is_no_unread_count_with_nobody_signed_in(): void
    {
        $this->send('One');

        $this->assertNull($this->get('/mailroom')->viewData('boxCounts')['unread']);
    }

    #[Test]
    public function mailer_counts_cover_every_mailer_that_captured_mail(): void
    {
        config()->set('mail.mailers.marketing', ['transport' => 'mailroom']);

        $this->send('Default one');
        $this->send('Default two');
        Mail::mailer('marketing')->raw('Hi.', fn ($message) => $message->to('kit@example.test')->subject('Campaign'));

        $this->assertSame(['mailroom' => 2, 'marketing' => 1], $this->get('/mailroom')->viewData('mailerCounts'));
    }

    #[Test]
    public function people_are_ranked_by_how_many_messages_they_appear_on(): void
    {
        $this->send('One', 'rachel@example.test');
        $this->send('Two', 'rachel@example.test', cc: ['kit@example.test']);
        $this->send('Three', 'sam@example.test', cc: ['rachel@example.test']);

        $people = $this->get('/mailroom')->viewData('people');

        // The app sent all three, so it leads; Reply-To and the envelope are
        // not counted, or everyone would appear twice.
        $this->assertSame(
            ['app@example.test', 'rachel@example.test', 'kit@example.test', 'sam@example.test'],
            $people->pluck('address')->all(),
        );

        $rachel = $people->firstWhere('address', 'rachel@example.test');
        $this->assertSame(3, $rachel->total);
        $this->assertSame(['to' => 2, 'cc' => 1], $rachel->roles);
    }

    #[Test]
    public function the_people_list_stops_at_ten_and_reports_how_many_there_are(): void
    {
        foreach (range(1, 12) as $n) {
            $this->send('To '.$n, "person{$n}@example.test");
        }

        $response = $this->get('/mailroom');

        $this->assertCount(10, $response->viewData('people'));
        // Twelve recipients and the app itself.
        $this->assertSame(13, $response->viewData('peopleTotal'));
    }

    #[Test]
    public function people_can_be_searched_by_address_or_name_ignoring_case(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('kit@example.test', 'Kit Lindqvist')->subject('One'));
        Mail::raw('Hi.', fn ($message) => $message->to('ops@acme.test', 'Kitchen Ops')->subject('Two'));
        Mail::raw('Hi.', fn ($message) => $message->to('sam@example.test')->subject('Three'));

        $response = $this->get('/mailroom?people=KIT');

        $this->assertSame(['kit@example.test', 'ops@acme.test'], $response->viewData('people')->pluck('address')->sort()->values()->all());
        $this->assertSame(2, $response->viewData('peopleTotal'));
    }

    /**
     * The name decides who matches, not which of their messages are counted:
     * clicking the person opens every message they are on, and the count has
     * to agree with that list.
     */
    #[Test]
    public function a_person_found_by_name_counts_every_message_they_are_on(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test', 'Rachel Okonkwo')->subject('Named'));
        $this->send('Unnamed', 'rachel@example.test');
        $this->send('Copied, unnamed', 'sam@example.test', cc: ['rachel@example.test']);

        $rachel = $this->get('/mailroom?people=okonkwo')->viewData('people')->sole();

        $this->assertSame(3, $rachel->total);
        $this->assertSame(['to' => 2, 'cc' => 1], $rachel->roles);
        $this->assertSame('Rachel Okonkwo', $rachel->name);
    }

    /**
     * Opening a message marks it read, but it stays in the Unread list it was
     * opened from until the reader moves on, as in any mail client. Otherwise
     * the open row vanishes and j and k lose their place.
     */
    #[Test]
    public function the_open_message_stays_in_unread_while_it_is_open(): void
    {
        $first = $this->send('First');
        $this->send('Second');

        $this->actingAs($this->reader());

        $open = $this->get("/mailroom/{$first->id}?box=unread");

        $this->assertSame(['Second', 'First'], $this->listed($open));
        // Listed, but read, so the heading agrees with the sidebar's count.
        $this->assertStringContainsString('<div class="mr-list-sub">1 unread</div>', $open->getContent());
        $this->assertSame(['Second'], $this->listed($this->get('/mailroom?box=unread')));
    }

    #[Test]
    public function people_search_takes_an_underscore_literally(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('first_last@example.test')->subject('One'));
        Mail::raw('Hi.', fn ($message) => $message->to('first.last@example.test')->subject('Two'));

        $response = $this->get('/mailroom?people=first_');

        $this->assertSame(['first_last@example.test'], $response->viewData('people')->pluck('address')->all());
        $this->assertSame(1, $response->viewData('peopleTotal'));
    }

    #[Test]
    public function people_search_skips_reply_to_and_envelope_addresses(): void
    {
        Mail::raw('Hi.', fn ($message) => $message->to('rachel@example.test')->replyTo('support@example.test')->subject('One'));

        $this->assertSame(0, $this->get('/mailroom?people=support')->viewData('peopleTotal'));
    }
}
