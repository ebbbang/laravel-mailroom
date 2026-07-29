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
            $table->uuid('uuid')->unique();
            $table->string('mailer')->index();

            $table->text('subject')->nullable();
            $table->string('message_id')->nullable()->index();

            // Addresses as they appear on the message itself.
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();

            // Addresses the transport was actually asked to deliver to. This
            // reflects Mail::alwaysTo() and includes BCC recipients, so it can
            // legitimately differ from the header addresses above.
            $table->json('envelope_recipients')->nullable();
            $table->string('envelope_sender')->nullable();

            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();

            $table->json('headers')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            $table->string('raw_path')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('attachment_count')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
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
        return config('test-mail.database.messages_table', 'test_mail_messages');
    }
};
