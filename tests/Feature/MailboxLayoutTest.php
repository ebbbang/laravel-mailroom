<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Models\MailroomRead;
use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The sidebar, the list and the reader as markup. The stylesheet is inlined
 * into every page, so each assertion here is scoped to the element it is
 * about rather than to the whole document.
 */
class MailboxLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    protected function send(string $subject = 'Hello', string $to = 'rachel@example.test'): MailroomMessage
    {
        Mail::raw('Body.', fn ($message) => $message->to($to)->subject($subject));

        return MailroomMessage::query()->latest('id')->firstOrFail();
    }

    protected function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);

        $this->assertNotFalse($from, "The page has no {$start}.");

        return substr($html, $from, (int) strpos($html, $end, $from) - $from + strlen($end));
    }

    protected function sidebar(string $html): string
    {
        return $this->between($html, '<nav class="mr-side"', '</nav>');
    }

    protected function reader(): User
    {
        $user = new User;
        $user->id = 7;

        return $user;
    }

    #[Test]
    public function deleting_asks_in_the_page_naming_the_subject_escaped(): void
    {
        $message = $this->send('Your <b>order</b> has shipped');

        $html = $this->get('/mailroom/'.$message->id)->assertOk()->getContent();

        $this->assertStringContainsString('data-mr-confirm="mr-confirm-delete"', $html);
        $this->assertStringNotContainsString('return confirm(', $html);

        $template = $this->between($html, '<template id="mr-confirm-delete">', '</template>');
        $this->assertStringContainsString('&ldquo;Your &lt;b&gt;order&lt;/b&gt; has shipped&rdquo;', $template);
        $this->assertStringNotContainsString('attachments', $template, 'A raw message has none to mention.');
        $this->assertStringContainsString('will be removed for good.', $template);

        $this->assertStringContainsString('id="mr-confirm"', $html);
    }

    #[Test]
    public function clear_all_asks_in_the_page_with_the_count(): void
    {
        $this->send('One');
        $this->send('Two');

        $html = $this->get('/mailroom')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-mr-confirm="mr-confirm-clear"'), 'Both copies of Clear all ask.');
        $this->assertMatchesRegularExpression(
            '#<span data-mr-confirm-title>\s*Delete all 2 messages\?\s*</span>#',
            $this->between($html, '<template id="mr-confirm-clear">', '</template>'),
        );
    }

    #[Test]
    public function an_empty_mailbox_has_nothing_to_confirm(): void
    {
        $html = $this->get('/mailroom')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mr-confirm"', $html);
        $this->assertStringNotContainsString("getElementById('mr-confirm-card')", $html);
    }

    #[Test]
    public function the_sidebar_offers_unread_only_to_someone_signed_in(): void
    {
        $this->send();

        $this->assertStringNotContainsString(
            '>Unread</span>',
            $this->sidebar($this->get('/mailroom')->getContent()),
        );

        $this->assertStringContainsString(
            '>Unread</span>',
            $this->sidebar($this->actingAs($this->reader())->get('/mailroom')->getContent()),
        );
    }

    #[Test]
    public function the_current_mailbox_is_marked(): void
    {
        $this->send();

        $sidebar = $this->sidebar($this->get('/mailroom?box=spam-checked')->getContent());

        $this->assertMatchesRegularExpression('#title="Spam checked"\s+aria-current="page"#', $sidebar);
        $this->assertDoesNotMatchRegularExpression('#title="Inbox"\s+aria-current="page"#', $sidebar);
    }

    #[Test]
    public function exactly_one_sidebar_item_is_current_and_a_person_outranks_the_rest(): void
    {
        $this->send();
        config()->set('mail.mailers.marketing', ['transport' => 'mailroom']);
        Mail::mailer('marketing')->raw('Hi.', fn ($message) => $message->to('kit@example.test')->subject('Campaign'));

        $sidebar = $this->sidebar($this->get('/mailroom?box=spam-checked&person=rachel@example.test&mailer=marketing')->getContent());

        $this->assertSame(1, substr_count($sidebar, 'aria-current='));
        $this->assertMatchesRegularExpression('#class="mr-person"\s+title="rachel@example\.test[^"]*"\s+aria-current="page"#', $sidebar);
    }

    #[Test]
    public function a_mailer_is_current_over_the_mailbox_it_narrows(): void
    {
        $this->send();
        config()->set('mail.mailers.marketing', ['transport' => 'mailroom']);
        Mail::mailer('marketing')->raw('Hi.', fn ($message) => $message->to('kit@example.test')->subject('Campaign'));

        $html = $this->get('/mailroom?mailer=marketing')->getContent();
        $sidebar = $this->sidebar($html);

        $this->assertSame(1, substr_count($sidebar, 'aria-current='));
        $this->assertMatchesRegularExpression('#title="marketing"\s+aria-current="page"#', $sidebar);
        $this->assertStringContainsString('<h2 class="mr-list-title">marketing</h2>', $html);
    }

    #[Test]
    public function each_sidebar_link_is_its_view_alone(): void
    {
        $this->send();
        config()->set('mail.mailers.marketing', ['transport' => 'mailroom']);
        Mail::mailer('marketing')->raw('Hi.', fn ($message) => $message->to('kit@example.test')->subject('Campaign'));

        // From a person's view, with a search and a mailer in the URL too.
        $sidebar = $this->sidebar($this->get('/mailroom?person=rachel@example.test&mailer=marketing&search=Hello')->getContent());

        $this->assertStringContainsString('href="'.e(route('mailroom.index')).'"', $sidebar, 'Inbox clears everything.');
        $this->assertStringContainsString('href="'.e(route('mailroom.index', ['box' => 'spam-checked'])).'"', $sidebar);
        $this->assertStringContainsString('href="'.e(route('mailroom.index', ['mailer' => 'marketing'])).'"', $sidebar);
        $this->assertStringContainsString('href="'.e(route('mailroom.index', ['person' => 'kit@example.test'])).'"', $sidebar);
    }

    #[Test]
    public function mailers_appear_only_when_there_is_more_than_one(): void
    {
        $this->send();

        $this->assertStringNotContainsString('<h2 class="mr-side-sec">Mailers</h2>', $this->sidebar($this->get('/mailroom')->getContent()));

        config()->set('mail.mailers.marketing', ['transport' => 'mailroom']);
        Mail::mailer('marketing')->raw('Hi.', fn ($message) => $message->to('kit@example.test')->subject('Campaign'));

        $sidebar = $this->sidebar($this->get('/mailroom')->getContent());

        $this->assertStringContainsString('<h2 class="mr-side-sec">Mailers</h2>', $sidebar);
        $this->assertStringContainsString('<span class="mr-nav-label">marketing</span>', $sidebar);
    }

    #[Test]
    public function the_people_search_and_its_script_arrive_with_the_first_mail(): void
    {
        $html = $this->get('/mailroom')->getContent();

        $this->assertStringNotContainsString('placeholder="Find a person"', $html);
        $this->assertStringNotContainsString("form.getAttribute('data-mr-people-endpoint')", $html);

        $this->send();

        $html = $this->get('/mailroom')->getContent();

        $this->assertStringContainsString('placeholder="Find a person"', $this->sidebar($html));
        $this->assertStringContainsString("form.getAttribute('data-mr-people-endpoint')", $html);
    }

    #[Test]
    public function past_ten_people_the_rest_are_counted_rather_than_listed(): void
    {
        foreach (range(1, 11) as $n) {
            $this->send('To '.$n, "person{$n}@example.test");
        }

        $sidebar = $this->sidebar($this->get('/mailroom')->getContent());

        // Eleven recipients and the app: ten listed, two counted.
        $this->assertSame(10, substr_count($sidebar, 'class="mr-person"'));
        $this->assertMatchesRegularExpression('#<p class="mr-people-more">\s*2 more &middot; type to find them\s*</p>#', $sidebar);
    }

    #[Test]
    public function the_people_endpoint_returns_the_matches_with_the_match_marked(): void
    {
        $this->send('One', 'kit@example.test');
        $this->send('Two', 'sam@example.test');

        $html = $this->get('/mailroom/people?q=kit')->assertOk()->getContent();

        $this->assertStringContainsString('<mark>kit</mark>@example.test', $html);
        $this->assertStringNotContainsString('sam@example.test', $html);
        $this->assertMatchesRegularExpression('#1 match\s*</p>#', $html);
        $this->assertStringNotContainsString('<nav', $html, 'A fragment, not a page.');
    }

    #[Test]
    public function the_people_endpoint_is_behind_the_same_gate_as_the_mailbox(): void
    {
        $this->send('One', 'kit@example.test');

        Gate::define('viewMailroom', fn (?User $user): bool => false);

        $this->get('/mailroom/people?q=kit')->assertForbidden();
    }

    #[Test]
    public function the_people_endpoint_says_when_no_one_matches(): void
    {
        $this->send();

        $this->assertStringContainsString(
            'No one matches &ldquo;zzz&rdquo;.',
            $this->get('/mailroom/people?q=zzz')->assertOk()->getContent(),
        );
    }

    #[Test]
    public function search_as_you_type_gets_the_list_alone(): void
    {
        $this->send('Your refund is on its way');
        $this->send('Something else');

        $html = $this->get('/mailroom?search=refund', ['X-Mailroom-Partial' => 'list'])->assertOk()->getContent();

        $this->assertStringContainsString('<nav class="mr-list"', $html);
        $this->assertStringNotContainsString('<nav class="mr-side"', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('Your <mark>refund</mark> is on its way', $html);
        $this->assertStringNotContainsString('Something else', $html);
    }

    /**
     * One URL answers with the page or the list alone, by request header. A
     * cache keyed on the URL would hand the bare list to Back after a search
     * has put that URL in the address bar.
     */
    #[Test]
    public function the_list_alone_is_never_cached_in_place_of_the_page(): void
    {
        $this->send();

        $partial = $this->get('/mailroom?search=Hello', ['X-Mailroom-Partial' => 'list']);
        $page = $this->get('/mailroom?search=Hello');

        $this->assertStringContainsString('X-Mailroom-Partial', (string) $partial->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $partial->headers->get('Cache-Control'));
        $this->assertStringContainsString('X-Mailroom-Partial', (string) $page->headers->get('Vary'));
    }

    #[Test]
    public function the_list_alone_marks_nothing_read(): void
    {
        $message = $this->send();

        $this->actingAs($this->reader())
            ->get('/mailroom/'.$message->id.'?search=Hello', ['X-Mailroom-Partial' => 'list'])
            ->assertOk();

        $this->assertSame(0, MailroomRead::query()->count());
    }

    /**
     * Searching closes the open message, as Gmail does, so the reader is
     * never left showing a message, its forms and its back link for a list
     * that is no longer on screen. The empty reader it falls back to ships
     * with the page.
     */
    #[Test]
    public function an_open_message_brings_the_empty_reader_for_a_search_to_show(): void
    {
        $message = $this->send();

        $template = $this->between($this->get('/mailroom/'.$message->id)->getContent(), '<template id="mr-reader-empty">', '</template>');

        $this->assertStringContainsString('Select a message', $template);
        $this->assertStringNotContainsString('<template id="mr-reader-empty">', $this->get('/mailroom')->getContent());
    }

    #[Test]
    public function deleting_returns_to_the_same_list(): void
    {
        $message = $this->send();
        $state = ['search' => 'Hello', 'mailer' => 'mailroom', 'box' => 'spam-checked', 'person' => 'rachel@example.test', 'page' => '2'];

        $form = $this->between(
            $this->get(route('mailroom.show', ['message' => $message->id] + $state))->getContent(),
            'action="'.e(route('mailroom.destroy', $message)).'"',
            '</form>',
        );

        foreach ($state as $key => $value) {
            $this->assertStringContainsString('name="'.$key.'" value="'.e($value).'"', $form);
        }

        $this->delete(route('mailroom.destroy', $message), $state)->assertRedirect(route('mailroom.index', $state));
    }

    /**
     * A row shows its first recipient and how many more there are. A bulk
     * send can carry thousands of Bcc and envelope rows, and loading them
     * for every row on every render is what this keeps from happening.
     */
    #[Test]
    public function the_list_loads_only_the_addresses_a_row_shows(): void
    {
        Mail::raw('Body.', fn ($message) => $message
            ->to(['rachel@example.test', 'sam@example.test', 'kit@example.test'])
            ->bcc(['dara@example.test', 'juergen@example.test'])
            ->subject('Bulk'));

        $response = $this->get('/mailroom');
        $item = $response->viewData('messages')->sole();

        // The first To, and the first envelope recipient for mail with no To.
        $this->assertSame(['rachel@example.test'], $item->addressesFor(AddressRole::To)->emails());
        $this->assertCount(1, $item->addressesFor(AddressRole::EnvelopeTo));
        $this->assertCount(2, $item->addresses);
        $this->assertStringContainsString('<span class="mr-item-more">+2</span>', $this->between($response->getContent(), '<nav class="mr-list"', '</nav>'));
    }

    #[Test]
    public function the_page_names_its_own_icon_inline(): void
    {
        $head = $this->between($this->get('/mailroom')->getContent(), '<head>', '</head>');

        $this->assertMatchesRegularExpression('/<link\s+rel="icon"\s+type="image\/svg\+xml"\s+href="data:image\/svg\+xml,%3Csvg/', $head);
    }

    #[Test]
    public function the_clear_button_leads_to_the_same_view_without_the_search(): void
    {
        $this->send();

        $html = $this->get('/mailroom?box=spam-checked&search=Hello')->getContent();
        $form = $this->between($html, '<form method="GET" action="'.e(route('mailroom.index')).'" class="mr-search"', '</form>');

        $this->assertStringContainsString('href="'.e(route('mailroom.index', ['box' => 'spam-checked'])).'"', $form);
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function bands(): array
    {
        return [
            'just under the warning' => [1.9, 'mr-badge-ok'],
            'at the warning' => [2.0, 'mr-badge-warn'],
            'just under the threshold' => [4.9, 'mr-badge-warn'],
            'at the threshold' => [5.0, 'mr-badge-bad'],
        ];
    }

    #[Test]
    #[DataProvider('bands')]
    public function a_spam_score_is_coloured_by_band(float $score, string $class): void
    {
        $message = $this->send();
        $message->forceFill(['spam_score' => $score, 'spam_rules' => [], 'spam_checked_at' => now()])->save();

        $tab = $this->between($this->get('/mailroom/'.$message->id)->getContent(), 'data-mr-tab="spam"', '</button>');

        $this->assertStringContainsString('class="mr-badge '.$class.'"', $tab);
    }

    #[Test]
    public function the_shell_says_when_a_message_is_being_read(): void
    {
        $message = $this->send();

        $this->assertStringContainsString('<div class="mr-app">', $this->get('/mailroom')->getContent());
        $this->assertStringContainsString('<div class="mr-app mr-reading">', $this->get('/mailroom/'.$message->id)->getContent());
    }

    #[Test]
    public function the_way_back_to_the_list_keeps_its_place(): void
    {
        $message = $this->send();
        $state = ['search' => 'Hello', 'box' => 'spam-checked', 'person' => 'rachel@example.test', 'page' => '2'];

        $back = $this->between(
            $this->get('/mailroom/'.$message->id.'?'.http_build_query($state))->getContent(),
            '<div class="mr-back">',
            '</div>',
        );

        $this->assertStringContainsString('href="'.e(route('mailroom.index', $state)).'"', $back);
        $this->assertStringContainsString('rachel@example.test', $back, 'The link should name the list it returns to.');
    }

    #[Test]
    public function an_empty_list_ships_no_keyboard_shortcuts(): void
    {
        $this->assertStringNotContainsString('j and k move through the list', $this->get('/mailroom')->getContent());

        $this->send();

        $this->assertStringContainsString('j and k move through the list', $this->get('/mailroom')->getContent());
    }
}
