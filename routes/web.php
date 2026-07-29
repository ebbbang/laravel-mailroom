<?php

use Ebbbang\TestMail\Http\Controllers\AttachmentController;
use Ebbbang\TestMail\Http\Controllers\ContentController;
use Ebbbang\TestMail\Http\Controllers\ExportController;
use Ebbbang\TestMail\Http\Controllers\MessageController;
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
});
