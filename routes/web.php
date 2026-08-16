<?php

use Ebbbang\Mailroom\Http\Controllers\AttachmentController;
use Ebbbang\Mailroom\Http\Controllers\AttachmentPreviewController;
use Ebbbang\Mailroom\Http\Controllers\ContentController;
use Ebbbang\Mailroom\Http\Controllers\ExportController;
use Ebbbang\Mailroom\Http\Controllers\ForwardController;
use Ebbbang\Mailroom\Http\Controllers\MessageController;
use Ebbbang\Mailroom\Mailroom;
use Illuminate\Support\Facades\Route;

Route::get('/', [MessageController::class, 'index'])->name('index');
Route::delete('/', [MessageController::class, 'clear'])->name('clear');

// Declared before the /{message} routes, and those are constrained to digits,
// so this can never be swallowed by the wildcard.
Route::get('/recent', [MessageController::class, 'recent'])->name('recent');

Route::middleware([])->whereNumber('message')->group(function (): void {
    Route::get('/{message}', [MessageController::class, 'index'])->name('show');
    Route::delete('/{message}', [MessageController::class, 'destroy'])->name('destroy');

    Route::get('/{message}/content/{format}', ContentController::class)
        ->whereIn('format', ['html', 'text', 'raw'])
        ->name('content');

    Route::get('/{message}/download/{format}', ExportController::class)
        ->whereIn('format', ['eml', 'html'])
        ->name('download');

    Route::get('/{message}/attachments/{attachment}', AttachmentController::class)
        ->whereNumber('attachment')
        ->name('attachment');

    // Not registered at all unless a mailer is configured to forward through,
    // so the feature has no surface until it is deliberately set up.
    if (Mailroom::canForward()) {
        Route::post('/{message}/forward', ForwardController::class)
            ->middleware('throttle:mailroom-forward')
            ->name('forward');
    }

    // Kept separate from the download route above, which forces
    // application/octet-stream and must keep doing so. See
    // AttachmentPreviewController for how serving real content types is made
    // safe. Not registered at all when previews are disabled.
    if (config('mailroom.preview.enabled', true)) {
        Route::get('/{message}/attachments/{attachment}/preview', AttachmentPreviewController::class)
            ->whereNumber('attachment')
            ->name('attachment.preview');
    }
});
