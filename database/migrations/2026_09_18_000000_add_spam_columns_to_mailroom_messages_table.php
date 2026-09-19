<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->table($this->table(), function (Blueprint $table): void {
            /*
             * The last SpamAssassin verdict, kept rather than refetched: a
             * check costs a round trip of several seconds, and a reader coming
             * back to a message wants to see what it scored, not wait again.
             *
             * Scores are signed, since rules that vouch for a message subtract
             * from the total.
             */
            $table->decimal('spam_score', 6, 2)->nullable();
            $table->json('spam_rules')->nullable();
            $table->timestamp('spam_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table($this->table(), function (Blueprint $table): void {
            $table->dropColumn(['spam_score', 'spam_rules', 'spam_checked_at']);
        });
    }

    public function getConnection(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function table(): string
    {
        return config('mailroom.database.messages_table', 'mailroom_messages');
    }
};
