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

            $table->foreignId('mailroom_message_id')
                ->constrained($this->messagesTable())
                ->cascadeOnDelete();

            /*
             * Whatever the application's guard calls its users, stringified: an
             * integer id on most, a UUID or ULID on anything using HasUuids or
             * HasUlids. Deliberately no foreign key, because captured mail may
             * sit on its own connection, where the users table is not visible
             * at all.
             */
            $table->string('reader_id')->index();

            $table->timestamp('read_at');

            // One row per person per message, so marking read twice is a no-op
            // rather than a pile of duplicates.
            $table->unique(['mailroom_message_id', 'reader_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    public function getConnection(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function table(): string
    {
        return config('mailroom.database.reads_table', 'mailroom_reads');
    }

    protected function messagesTable(): string
    {
        return config('mailroom.database.messages_table', 'mailroom_messages');
    }
};
