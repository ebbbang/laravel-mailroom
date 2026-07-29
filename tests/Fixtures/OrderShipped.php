<?php

namespace Ebbbang\TestMail\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $orderNumber = 'A-1001') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf('Order %s shipped', $this->orderNumber));
    }

    public function content(): Content
    {
        return new Content(
            text: 'test-mail-tests::order-shipped-text',
            with: ['orderNumber' => $this->orderNumber],
            htmlString: '<h1>On its way</h1><p>Order '.$this->orderNumber.' has shipped.</p>',
        );
    }
}
