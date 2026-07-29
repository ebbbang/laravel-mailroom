<?php

namespace Workbench\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

class DemoOrderShipped extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $orderNumber = 'A-1001',
        public string $customer = 'Rachel Okonkwo',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Your order %s is on its way', $this->orderNumber),
            using: [
                function ($message): void {
                    $message->getHeaders()->add(new TagHeader('shipping'));
                    $message->getHeaders()->add(new MetadataHeader('order_id', $this->orderNumber));
                    $message->getHeaders()->addTextHeader('X-Campaign', 'transactional');
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'workbench::mail.order-shipped',
            text: 'workbench::mail.order-shipped-text',
            // Resolved here rather than in the view: __DIR__ inside a
            // compiled Blade template points at the compiled-view cache.
            with: ['logoPath' => realpath(__DIR__.'/../../resources/assets/logo.png')],
        );
    }
}
