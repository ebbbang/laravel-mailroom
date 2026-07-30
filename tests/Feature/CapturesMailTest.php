<?php

namespace Ebbbang\Mailroom\Tests\Feature;

use Ebbbang\Mailroom\Events\MessageStored;
use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Tests\Fixtures\OrderShipped;
use Ebbbang\Mailroom\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class CapturesMailTest extends TestCase
{
    #[Test]
    public function it_captures_a_raw_message(): void
    {
        Mail::raw('Hello from the raw helper.', function ($message): void {
            $message->to('rachel@example.test')->subject('A raw message');
        });

        $captured = MailroomMessage::sole();

        $this->assertSame('A raw message', $captured->subject);
        $this->assertSame('rachel@example.test', $captured->to[0]['address']);
        $this->assertSame('app@example.test', $captured->from[0]['address']);
        $this->assertStringContainsString('Hello from the raw helper.', (string) $captured->text_body);
        $this->assertSame('mailroom', $captured->mailer);
    }

    #[Test]
    public function it_captures_a_mailable_with_html_and_text_bodies(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped('A-1001'));

        $captured = MailroomMessage::sole();

        $this->assertSame('Order A-1001 shipped', $captured->subject);
        $this->assertStringContainsString('<h1>On its way</h1>', (string) $captured->html_body);
        $this->assertStringContainsString('Order A-1001 has shipped.', (string) $captured->text_body);
    }

    #[Test]
    public function it_records_cc_and_reply_to_and_keeps_bcc_out_of_the_eml(): void
    {
        Mail::to('rachel@example.test')
            ->cc('cc@example.test')
            ->bcc('hidden@example.test')
            ->send((new OrderShipped)->replyTo('support@example.test'));

        $captured = MailroomMessage::sole();

        $this->assertSame('cc@example.test', $captured->cc[0]['address']);
        $this->assertSame('hidden@example.test', $captured->bcc[0]['address']);
        $this->assertSame('support@example.test', $captured->reply_to[0]['address']);

        // Symfony strips Bcc when rendering, so it must not reach the file --
        // but the envelope still had to deliver there.
        $raw = $captured->raw();
        $this->assertStringNotContainsString('hidden@example.test', (string) $raw);
        $this->assertContains(
            'hidden@example.test',
            array_column($captured->envelope_recipients, 'address')
        );
    }

    #[Test]
    public function the_stored_message_id_matches_the_one_written_into_the_eml(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $captured = MailroomMessage::sole();

        $this->assertNotNull($captured->message_id);
        $this->assertStringContainsString(
            $captured->message_id,
            (string) $captured->raw(),
            'The Message-ID column must match the one in the stored .eml.'
        );
    }

    #[Test]
    public function it_records_tags_metadata_and_custom_headers(): void
    {
        Mail::to('rachel@example.test')->send(
            (new OrderShipped)->withSymfonyMessage(function ($message): void {
                $message->getHeaders()->add(new TagHeader('shipping'));
                $message->getHeaders()->add(new MetadataHeader('order_id', '1001'));
                $message->getHeaders()->addTextHeader('X-Campaign', 'summer-sale');
            })
        );

        $captured = MailroomMessage::sole();

        $this->assertSame(['shipping'], $captured->tags);
        $this->assertSame(['order_id' => '1001'], $captured->metadata);
        $this->assertSame('summer-sale', $captured->headers['X-Campaign']);

        // Bucketed headers must not be duplicated into the generic bag.
        $this->assertArrayNotHasKey('X-Tag', $captured->headers);
        $this->assertArrayNotHasKey('X-Metadata-order_id', $captured->headers);
    }

    #[Test]
    public function it_captures_mail_redirected_by_always_to(): void
    {
        Mail::alwaysTo('redirected@example.test');

        try {
            Mail::to('rachel@example.test')->cc('cc@example.test')->send(new OrderShipped);
        } finally {
            Mail::alwaysTo(null);
        }

        $captured = MailroomMessage::sole();

        // alwaysTo rewrites the message's own To header and forgets cc/bcc
        // rather than only redirecting the envelope, so the captured message
        // records the redirect and no divergence exists to report.
        $this->assertSame('redirected@example.test', $captured->to[0]['address']);
        $this->assertSame([], $captured->cc);
        $this->assertSame('redirected@example.test', $captured->envelope_recipients[0]['address']);
        $this->assertFalse($captured->envelopeDivergesFromHeaders());
    }

    #[Test]
    public function it_flags_a_message_whose_envelope_does_not_match_its_headers(): void
    {
        // Divergence comes from a caller handing the transport an envelope
        // that disagrees with the message headers, which is what the mailbox
        // warns about -- Laravel's own helpers keep the two in step.
        $email = (new Email)
            ->from('app@example.test')
            ->to('rachel@example.test')
            ->subject('Diverging envelope')
            ->text('Body');

        resolve('mailer')->getSymfonyTransport()->send(
            $email,
            new Envelope(new Address('app@example.test'), [new Address('elsewhere@example.test')])
        );

        $captured = MailroomMessage::sole();

        $this->assertSame('rachel@example.test', $captured->to[0]['address']);
        $this->assertSame('elsewhere@example.test', $captured->envelope_recipients[0]['address']);
        $this->assertTrue($captured->envelopeDivergesFromHeaders());
    }

    #[Test]
    public function it_records_the_mailer_that_sent_the_message(): void
    {
        config()->set('mail.mailers.secondary', ['transport' => 'mailroom']);

        Mail::mailer('secondary')->to('rachel@example.test')->send(new OrderShipped);

        $this->assertSame('secondary', MailroomMessage::sole()->mailer);
    }

    #[Test]
    public function it_captures_queued_mailables(): void
    {
        Mail::to('rachel@example.test')->queue(new OrderShipped);

        $this->assertSame(1, MailroomMessage::query()->count());
    }

    #[Test]
    public function it_fires_a_message_stored_event(): void
    {
        Event::fake([MessageStored::class]);

        Mail::to('rachel@example.test')->send(new OrderShipped);

        Event::assertDispatched(MessageStored::class, fn (MessageStored $event): bool => $event->message->subject === 'Order A-1001 shipped');
    }

    #[Test]
    public function it_stores_the_raw_mime_on_disk_and_records_its_size(): void
    {
        Mail::to('rachel@example.test')->send(new OrderShipped);

        $captured = MailroomMessage::sole();

        $this->assertTrue($captured->hasRaw());
        $this->assertSame(strlen((string) $captured->raw()), $captured->size);
        $this->assertStringContainsString('Subject: Order A-1001 shipped', (string) $captured->raw());
    }
}
