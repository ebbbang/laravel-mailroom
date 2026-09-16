<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Workbench\App\Mail\DemoOrderShipped;
use Workbench\App\Support\Fixtures;

/*
|--------------------------------------------------------------------------
| Workbench routes
|--------------------------------------------------------------------------
|
| A tiny demo app for driving the package by hand:
|
|   composer serve
|   open http://127.0.0.1:8000/send
|
*/

Route::get('/', fn (): Redirector|RedirectResponse => redirect('/mailroom'));

/*
 * Sign-in for the demo, so read state has somebody to belong to: /login/rachel,
 * /login/sam, and so on for any seeded person, then /logout.
 *
 * The package ships no login of its own and defers to the host application's.
 * This is that application, and it is the only place a password appears.
 *
 * Both are GET because the demo is driven by typing URLs and has no page to
 * hang a form on. That would be the wrong shape for a real application, and it
 * never reaches one: workbench is export-ignored.
 */
Route::get('/login/{person}', function (string $person): Redirector|RedirectResponse {
    $email = $person.'@example.test';

    $user = User::query()->firstWhere('email', $email) ?? User::forceCreate([
        'name' => Str::headline($person),
        'email' => $email,
        'password' => Hash::make('mailroom'),
    ]);

    Auth::login($user);

    return redirect('/mailroom');
});

Route::get('/logout', function (): Redirector|RedirectResponse {
    Auth::logout();

    return redirect('/mailroom');
});

Route::get('/send', function (): Redirector|RedirectResponse {
    $invoice = "INVOICE\n=======\n\nOrder A-1001\nTotal: 49.00\n";
    $terms = "1. Goods remain ours until paid for.\n2. Returns accepted within 30 days.\n";

    Mail::to('rachel@example.test')
        ->cc('accounts@example.test')
        ->bcc('audit@example.test')
        ->send(
            (new DemoOrderShipped('A-'.random_int(1000, 9999)))
                ->attachData($invoice, 'invoice.txt', ['mime' => 'text/plain'])
                ->attachData($terms, 'terms.txt', ['mime' => 'text/plain'])
        );

    return redirect('/mailroom');
});

/*
 * One message carrying every previewable kind, plus a .docx to exercise the
 * "no preview" path. Fixtures are generated on the fly so the repository holds
 * no binaries.
 */
Route::get('/send-attachments', function (): Redirector|RedirectResponse {
    $mailable = (new DemoOrderShipped('A-'.random_int(1000, 9999)))
        ->attachData(Fixtures::png(), 'screenshot.png', ['mime' => 'image/png'])
        ->attachData(Fixtures::svg(), 'logo.svg', ['mime' => 'image/svg+xml'])
        ->attachData(Fixtures::pdf(), 'invoice.pdf', ['mime' => 'application/pdf'])
        ->attachData(Fixtures::wav(), 'notification.wav', ['mime' => 'audio/wav'])
        ->attachData(Fixtures::csv(), 'orders.csv', ['mime' => 'text/csv'])
        ->attachData(Fixtures::json(), 'payload.json', ['mime' => 'application/json'])
        ->attachData(Fixtures::ics(), 'delivery.ics', ['mime' => 'text/calendar'])
        ->attachData(Fixtures::eml(), 'forwarded.eml', ['mime' => 'message/rfc822'])
        ->attachData("Order A-1001\n============\n\nPacked by: warehouse\n", 'notes.txt', ['mime' => 'text/plain'])
        ->attachData("<p>An emailed HTML file.</p>\n<script>alert('never runs')</script>\n", 'report.html', ['mime' => 'text/html']);

    if (($docx = Fixtures::docx()) !== null) {
        $mailable->attachData($docx, 'invoice.docx', [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    Mail::to('rachel@example.test')->cc('accounts@example.test')->send($mailable);

    return redirect('/mailroom');
});

Route::get('/send-plain', function (): Redirector|RedirectResponse {
    Mail::raw("A plain text message with no HTML part at all.\n\nSent at ".now(), function ($message): void {
        $message->to('rachel@example.test')->subject('Plain text only');
    });

    return redirect('/mailroom');
});
