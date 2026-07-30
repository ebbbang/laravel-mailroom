<?php

namespace Workbench\App\Console\Commands;

use Ebbbang\Mailroom\Models\MailroomMessage;
use Ebbbang\Mailroom\Storage\RawMessageStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Workbench\App\Mail\DemoOrderShipped;
use Workbench\App\Mail\PackingNote;
use Workbench\App\Support\Fixtures;

/**
 * Fills the demo mailbox with data that reaches every branch of the package's
 * UI, so all of it can be inspected in a browser without composing anything.
 *
 * Everything here is sent through the mail stack rather than inserted into the
 * tables. Inserting rows would be quicker but would produce messages that had
 * never been through the transport: no raw MIME on disk, so .eml export, the
 * Raw tab and every binary preview would be broken. Sending means each row is
 * genuine, at the cost of the seeder taking a few seconds.
 */
class SeedMailboxCommand extends Command
{
    protected $signature = 'demo:seed
                            {--fresh : Clear the mailbox first}
                            {--filler=55 : How many ordinary messages to pad the list with}';

    protected $description = 'Fill the demo mailbox with one message per scenario, plus realistic filler';

    /**
     * Structured rather than "Name <address>" strings: Mail::to() treats each
     * string in an array as a bare address, so a display name has to be its
     * own key or it fails RFC validation.
     *
     * @var array<int, array{email: string, name: string}>
     */
    protected array $people = [
        ['email' => 'rachel@example.test', 'name' => 'Rachel Okonkwo'],
        ['email' => 'sam@example.test', 'name' => 'Sam Ihejirika'],
        ['email' => 'dara@example.test', 'name' => 'Dara Adeyemi'],
        ['email' => 'kit@example.test', 'name' => 'Kit Lindqvist'],
        ['email' => 'yuki@example.test', 'name' => 'Yuki Tanaka'],
    ];

    public function handle(RawMessageStore $store): int
    {
        if ($this->option('fresh')) {
            $this->callSilently('mailroom:clear', ['--force' => true]);
            $this->components->info('Cleared the mailbox.');
        } elseif (MailroomMessage::query()->exists()) {
            // Keeps this safe to chain into `composer serve`.
            $this->components->info(sprintf(
                'Mailbox already holds %d message(s) — nothing to do. Use --fresh to reseed.',
                MailroomMessage::query()->count()
            ));

            return self::SUCCESS;
        }

        // A second mailer so the list shows the mailer badge and the header
        // grows a filter. Only the recorded name matters afterwards: the
        // filter reads distinct values straight out of the database.
        config()->set('mail.mailers.secondary', ['transport' => 'mailroom']);

        $this->components->task('scenario messages', fn () => $this->scenarios($store));
        $this->components->task('filler messages', fn () => $this->filler((int) $this->option('filler')));

        $this->newLine();
        $this->components->info(sprintf('Seeded %d messages.', MailroomMessage::query()->count()));
        $this->components->bulletList([
            'Visit /'.config('mailroom.path', 'mailroom').' to browse them.',
            'Subjects are prefixed with the scenario they demonstrate.',
            'Ages span 45 days, so `mailroom:prune --days=7` and `--days=30` both do something.',
        ]);

        return self::SUCCESS;
    }

    protected function scenarios(RawMessageStore $store): void
    {
        $this->everyAttachmentKind();
        $this->bodyShapes();
        $this->addressingAndMetadata();
        $this->awkwardContent();
        $this->attachmentEdgeCases();
        $this->brokenStates($store);
        $this->otherMailers();
    }

    /**
     * One message carrying every kind the mailbox can preview, plus a .docx
     * for the "no preview" path.
     */
    protected function everyAttachmentKind(): void
    {
        $mailable = (new DemoOrderShipped('A-1001'))
            ->titled('[all kinds] Every previewable attachment type')
            ->attachData(Fixtures::png(), 'screenshot.png', ['mime' => 'image/png'])
            ->attachData(Fixtures::jpeg(), 'photo.jpg', ['mime' => 'image/jpeg'])
            ->attachData(Fixtures::gif(), 'animation.gif', ['mime' => 'image/gif'])
            ->attachData(Fixtures::webp(), 'compressed.webp', ['mime' => 'image/webp'])
            ->attachData(Fixtures::svg(), 'logo.svg', ['mime' => 'image/svg+xml'])
            ->attachData(Fixtures::pdf(), 'invoice.pdf', ['mime' => 'application/pdf'])
            ->attachData(Fixtures::wav(), 'notification.wav', ['mime' => 'audio/wav'])
            ->attachData(Fixtures::mp4(), 'clip.mp4', ['mime' => 'video/mp4'])
            ->attachData("Order A-1001\n============\n\nPacked by: warehouse\n", 'notes.txt', ['mime' => 'text/plain'])
            ->attachData(Fixtures::markdown(), 'packing.md', ['mime' => 'text/markdown'])
            ->attachData(Fixtures::csv(), 'orders.csv', ['mime' => 'text/csv'])
            ->attachData(Fixtures::tsv(), 'orders.tsv', ['mime' => 'text/tab-separated-values'])
            ->attachData(Fixtures::json(), 'payload.json', ['mime' => 'application/json'])
            ->attachData(Fixtures::xml(), 'order.xml', ['mime' => 'application/xml'])
            ->attachData(Fixtures::yaml(), 'config.yaml', ['mime' => 'application/yaml'])
            ->attachData(Fixtures::sql(), 'snapshot.sql', ['mime' => 'application/sql'])
            ->attachData("<p>An emailed HTML file.</p>\n<script>alert('never runs')</script>\n", 'report.html', ['mime' => 'text/html'])
            ->attachData(Fixtures::ics(), 'delivery.ics', ['mime' => 'text/calendar'])
            ->attachData(Fixtures::eml(), 'forwarded.eml', ['mime' => 'message/rfc822']);

        if (($docx = Fixtures::docx()) !== null) {
            $mailable->attachData($docx, 'invoice.docx', [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
        }

        $this->sendAged($mailable, 'rachel@example.test', hoursAgo: 1);
    }

    /**
     * The three ways the detail pane can open -- HTML, Text, or straight to
     * Headers when a message has no body at all.
     */
    protected function bodyShapes(): void
    {
        $this->aged(fn () => Mail::html(
            '<h1>HTML only</h1><p>This message has no plain text alternative, so there is no Text tab.</p>',
            fn ($message) => $message->to('sam@example.test')->subject('[html only] No text alternative')
        ), hoursAgo: 3);

        $this->aged(fn () => Mail::raw(
            "Text only.\n\nThere is no HTML part, so the detail pane opens on Text instead.",
            fn ($message) => $message->to('sam@example.test')->subject('[text only] No HTML part')
        ), hoursAgo: 4);

        /*
         * No body at all, so the detail pane opens on Headers and the list
         * shows no preview line. It still needs an attachment: Symfony rejects
         * a message with neither a body nor attachments outright, so a
         * genuinely empty message is not a state that can exist.
         */
        $this->aged(fn () => Mail::send([], [], fn ($message) => $message
            ->to('sam@example.test')
            ->subject('[no body] Headers only, nothing to render')
            ->attachData('The only content this message carries.', 'attached.txt', ['mime' => 'text/plain'])), hoursAgo: 5);

        $this->aged(fn () => Mail::html(
            '<p>The subject line of this message is empty.</p>',
            fn ($message) => $message->to('dara@example.test')->subject('')
        ), hoursAgo: 6);

        $this->aged(fn () => Mail::to('dara@example.test')->send(new PackingNote('A-1002')), hoursAgo: 7);
    }

    /**
     * Cc, Bcc, Reply-To, tags, metadata and a custom header, so every row of
     * the detail table and both badge styles appear.
     */
    protected function addressingAndMetadata(): void
    {
        $mailable = (new DemoOrderShipped('A-1003'))
            ->titled('[addressing] Cc, Bcc, Reply-To, tags and metadata')
            ->replyTo('support@example.test', 'Support')
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()->add(new TagHeader('shipping'));
                $email->getHeaders()->add(new TagHeader('priority'));
                $email->getHeaders()->add(new MetadataHeader('order_id', 'A-1003'));
                $email->getHeaders()->add(new MetadataHeader('warehouse', 'LDN-2'));
                $email->getHeaders()->addTextHeader('X-Campaign', 'transactional');
            });

        $this->aged(fn () => Mail::to(['rachel@example.test', 'sam@example.test'])
            ->cc('accounts@example.test')
            ->bcc('audit@example.test')
            ->send($mailable), hoursAgo: 9);

        // A long subject and a crowd of recipients, for truncation.
        $this->aged(fn () => Mail::to($this->people)
            ->cc(['finance@example.test', 'ops@example.test', 'legal@example.test'])
            ->send((new DemoOrderShipped('A-1004'))->titled(
                '[long] '.str_repeat('An unreasonably long subject line that ought to be truncated somewhere sensible. ', 3)
            )), hoursAgo: 11);
    }

    /**
     * Content that tends to break layouts and encodings.
     */
    protected function awkwardContent(): void
    {
        $this->aged(fn () => Mail::html(
            '<h1>配送のお知らせ 📦</h1>'
            .'<p>ご注文 A-1005 を発送しました。</p>'
            .'<p dir="rtl">تم إرسال طلبك بنجاح. شكرًا لك!</p>'
            .'<p>Ünïcödé, emoji 🎉🚚✅, and a zero-width space:[&#8203;]</p>',
            fn ($message) => $message->to('yuki@example.test')->subject('[unicode] 配送のお知らせ 📦 · تم الإرسال')
        ), hoursAgo: 13);

        $paragraphs = collect(range(1, 60))
            ->map(fn (int $n): string => '<p>Paragraph '.$n.'. '.str_repeat('This body is long enough to need scrolling inside the preview pane. ', 4).'</p>')
            ->implode("\n");

        $this->aged(fn () => Mail::html(
            '<h1>A long message</h1>'.$paragraphs,
            fn ($message) => $message->to('kit@example.test')->subject('[long body] Sixty paragraphs of scrolling')
        ), hoursAgo: 15);

        // Embedded images and no file attachments -- the Attachments tab has to
        // appear anyway to surface the inline count.
        $this->aged(fn () => Mail::send([], [], function ($message): void {
            $first = $message->embedData(Fixtures::png(160, 90), 'header.png', 'image/png');
            $second = $message->embedData(Fixtures::png(160, 90), 'footer.png', 'image/png');

            $message->to('kit@example.test')
                ->subject('[inline only] Embedded images, nothing attached')
                ->html('<p><img src="'.$first.'"></p><p>Body text between them.</p><p><img src="'.$second.'"></p>');
        }), hoursAgo: 17);
    }

    /**
     * Attachments that exercise the preview states rather than the kinds.
     */
    protected function attachmentEdgeCases(): void
    {
        $this->aged(fn () => Mail::to('rachel@example.test')->send(
            (new DemoOrderShipped('A-1006'))
                ->titled('[preview states] Too large, empty, malformed and truncated')
                ->attachData(Fixtures::oversizedText(), 'huge.log', ['mime' => 'text/plain'])
                ->attachData('', 'empty.txt', ['mime' => 'text/plain'])
                ->attachData(Fixtures::brokenJson(), 'malformed.json', ['mime' => 'application/json'])
                ->attachData(Fixtures::longCsv(), 'five-hundred-rows.csv', ['mime' => 'text/csv'])
        ), hoursAgo: 19);

        // A filename that must not be able to escape the storage directory,
        // and a type the mailbox refuses to preview.
        $this->aged(fn () => Mail::to('rachel@example.test')->send(
            (new DemoOrderShipped('A-1007'))
                ->titled('[hostile names] Traversal attempt and unknown types')
                ->attachData('not actually a password file', '../../../etc/passwd', ['mime' => 'text/plain'])
                ->attachData('binary junk', 'installer.exe', ['mime' => 'application/x-msdownload'])
                ->attachData('PK archive', 'bundle.zip', ['mime' => 'application/zip'])
        ), hoursAgo: 21);

        // Bytes skipped for being over the configured ceiling. Restored
        // afterwards so nothing else in the seed is affected.
        $original = config('mailroom.storage.max_attachment_size');
        config()->set('mailroom.storage.max_attachment_size', 512);

        try {
            $this->aged(fn () => Mail::to('rachel@example.test')->send(
                (new DemoOrderShipped('A-1008'))
                    ->titled('[skipped] Attachment over storage.max_attachment_size')
                    ->attachData(Fixtures::png(320, 180), 'too-big.png', ['mime' => 'image/png'])
            ), hoursAgo: 23);
        } finally {
            config()->set('mailroom.storage.max_attachment_size', $original);
        }
    }

    /**
     * The two amber notices, which both need something to be wrong.
     */
    protected function brokenStates(RawMessageStore $store): void
    {
        // Envelope recipients that disagree with the headers. Laravel's own
        // helpers keep the two in step, so this goes through the transport
        // directly with an envelope of its own.
        $this->aged(function (): void {
            $email = (new Email)
                ->from(new Address('app@example.test', 'Example App'))
                ->to(new Address('rachel@example.test', 'Rachel Okonkwo'))
                ->subject('[envelope] Delivered somewhere other than the To header')
                ->text('The envelope for this message named a different recipient.')
                ->html('<p>The envelope for this message named a different recipient.</p>');

            Mail::mailer('mailroom')->getSymfonyTransport()->send(
                $email,
                new Envelope(new Address('app@example.test'), [new Address('interceptor@example.test')])
            );
        }, hoursAgo: 25);

        // Blobs deleted after capture, as an ephemeral disk would do. Reaches
        // "Stored files are missing", the isMissing() attachment rows, and the
        // 'unreadable' text preview state.
        $this->aged(fn () => Mail::to('rachel@example.test')->send(
            (new DemoOrderShipped('A-1009'))
                ->titled('[missing files] Row survived, blobs did not')
                ->attachData(Fixtures::png(), 'gone.png', ['mime' => 'image/png'])
                ->attachData("This text file's bytes were deleted after capture.\n", 'gone.txt', ['mime' => 'text/plain'])
        ), hoursAgo: 27);

        $orphaned = MailroomMessage::query()->latest('id')->first();

        if ($orphaned !== null) {
            $store->deleteMessage($orphaned->uuid);
        }
    }

    /**
     * A second mailer, so the list shows mailer badges and the header grows a
     * filter dropdown. Plus a queued mailable.
     */
    protected function otherMailers(): void
    {
        $this->aged(fn () => Mail::mailer('secondary')
            ->to('ops@example.test')
            ->send((new DemoOrderShipped('A-1010'))->titled('[secondary mailer] Captured from a second mailer')), hoursAgo: 29);

        $this->aged(fn () => Mail::to('ops@example.test')
            ->queue((new DemoOrderShipped('A-1011'))->titled('[queued] Sent through the queue')), hoursAgo: 31);
    }

    /**
     * Ordinary-looking mail, for pagination, search and a spread of ages.
     */
    protected function filler(int $count): void
    {
        // Subject and body paired, so the list preview line matches the subject
        // rather than reading like a different email.
        $templates = [
            ['Your order %s has shipped', 'Order %s left the warehouse this morning and is with the courier.'],
            ['Invoice %s is ready', 'The invoice for order %s is attached and due on receipt.'],
            ['Order %s is out for delivery', 'Order %s is on the van and should arrive before 18:00.'],
            ['We could not take payment for %s', 'The card on file was declined for order %s. Please update it.'],
            ['Order %s has been delivered', 'Order %s was signed for at the door. Thanks for shopping with us.'],
            ['Your refund for %s is on its way', 'The refund for order %s has been issued and takes 3-5 days.'],
            ['Delivery window confirmed for %s', 'Order %s will arrive between 09:00 and 13:00 next Tuesday.'],
            ['Order %s is being packed', 'Order %s is being picked and packed and will ship shortly.'],
        ];

        for ($i = 0; $i < $count; $i++) {
            $reference = sprintf('B-%04d', 2000 + $i);
            [$subjectTemplate, $bodyTemplate] = $templates[$i % count($templates)];
            $subject = sprintf($subjectTemplate, $reference);
            $body = sprintf($bodyTemplate, $reference);
            $person = $this->people[$i % count($this->people)];

            $attachment = $i % 3 === 0
                ? ['name' => 'invoice-'.$reference.'.pdf', 'data' => Fixtures::pdf($subject)]
                : null;

            // Spread across 45 days, newest first, so pruning at either cutoff
            // has work to do.
            $this->aged(
                fn () => Mail::html(
                    sprintf('<h2>%s</h2><p>%s</p>', e($subject), e($body)),
                    function ($message) use ($person, $subject, $attachment): void {
                        $message->to($person['email'], $person['name'])->subject($subject);

                        if ($attachment !== null) {
                            $message->attachData($attachment['data'], $attachment['name'], ['mime' => 'application/pdf']);
                        }
                    }
                ),
                hoursAgo: (int) round(36 + ($i / max($count - 1, 1)) * (45 * 24 - 36)),
            );
        }
    }

    /**
     * Send, then backdate. Mail is always captured at "now", so the age has to
     * be applied afterwards -- quietly, since a timestamp correction has no
     * business firing model events.
     */
    protected function aged(callable $send, int $hoursAgo): void
    {
        $before = MailroomMessage::query()->max('id');

        $send();

        MailroomMessage::query()
            ->when($before !== null, fn ($query) => $query->where('id', '>', $before))
            ->get()
            ->each(function (MailroomMessage $message) use ($hoursAgo): void {
                $at = Date::now()->subHours($hoursAgo);

                $message->forceFill(['created_at' => $at, 'updated_at' => $at, 'sent_at' => $at])->saveQuietly();
            });
    }

    protected function sendAged(object $mailable, string $to, int $hoursAgo): void
    {
        $this->aged(fn () => Mail::to($to)->send($mailable), $hoursAgo);
    }
}
