<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Opening a message is a full page load, so the list only keeps its place when
 * every row link carries the state the list was built from. The scroll offset
 * is restored client side and keyed on those same values, which makes the links
 * the half of it that can be pinned here.
 */
class ListNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewMailroom', fn (?User $user): bool => true);
    }

    /**
     * The decoded query of every message link in the list.
     *
     * The hrefs come out of Blade HTML-escaped, so "&" arrives as "&amp;" and
     * has to be decoded before the query can be read.
     *
     * @return array<int, array<string, string>>
     */
    protected function rowQueries(string $html): array
    {
        preg_match_all('#href="([^"]+)"\s+class="mr-item"#', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The list rendered no message rows.');

        return array_map(function (string $href): array {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);

            return $query;
        }, $matches[1]);
    }

    #[Test]
    public function opening_a_message_keeps_the_page_and_filters_in_every_row_link(): void
    {
        config()->set('mailroom.ui.per_page', 2);

        foreach (range(1, 6) as $n) {
            Mail::to('rachel@example.test')->send(new OrderShipped('A-'.$n));
        }

        // A message on the second page, opened with a search and a mailer
        // filter in place: every value the list was built from.
        $state = ['search' => 'rachel@example.test', 'mailer' => 'mailroom', 'page' => '2'];

        $selected = MailroomMessage::query()->latest('id')->skip(2)->firstOrFail();

        $html = $this->get('/mailroom/'.$selected->id.'?'.http_build_query($state))
            ->assertOk()
            ->getContent();

        foreach ($this->rowQueries($html) as $query) {
            foreach ($state as $key => $value) {
                $this->assertSame(
                    $value,
                    $query[$key] ?? null,
                    "A row link dropped \"{$key}\", so opening that message would lose the list's place."
                );
            }
        }
    }
}
