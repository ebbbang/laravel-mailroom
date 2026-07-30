<?php

namespace Workbench\App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A markdown mailable, so the seeder covers Laravel's markdown renderer as
 * well as plain views -- it produces a different HTML/text pair to anything
 * the other demo messages generate.
 */
class PackingNote extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $orderNumber = 'A-1001') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[markdown] Packing note for '.$this->orderNumber);
    }

    public function content(): Content
    {
        return new Content(markdown: 'workbench::mail.packing-note');
    }
}
