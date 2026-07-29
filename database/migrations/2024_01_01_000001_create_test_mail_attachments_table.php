<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table): void {
            $table->id();

            $table->foreignId('test_mail_message_id')
                ->constrained($this->messagesTable())
                ->cascadeOnDelete();

            $table->string('filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // "attachment" or "inline". Inline parts are the images produced by
            // $message->embed() and are referenced from the HTML body by cid:.
            $table->string('disposition')->default('attachment');
            $table->string('content_id')->nullable()->index();

            // Null when the bytes were skipped (see storage.max_attachment_size).
            $table->string('path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    public function getConnection(): ?string
    {
        return config('test-mail.database.connection');
    }

    protected function table(): string
    {
        return config('test-mail.database.attachments_table', 'test_mail_attachments');
    }

    protected function messagesTable(): string
    {
        return config('test-mail.database.messages_table', 'test_mail_messages');
    }
};
