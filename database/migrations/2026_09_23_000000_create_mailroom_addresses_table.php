<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves every address off the messages table and into rows of its own.
 *
 * A JSON array cannot be filtered by membership on SQLite, where
 * whereJsonContains compiles to a scalar comparison and matches nothing, and
 * a text search over encoded JSON matches the key names "address" and "name"
 * while missing any name stored as \u escapes. envelope_sender is a plain
 * string, but it is an address too, so it moves with the rest and addresses
 * live in exactly one place.
 *
 * One migration rather than three, so there is never a deployed state in
 * which both copies exist and can disagree.
 */
return new class extends Migration
{
    /**
     * Column on the messages table => the role its addresses take here.
     *
     * @var array<string, string>
     */
    protected array $jsonColumns = [
        'from' => 'from',
        'to' => 'to',
        'cc' => 'cc',
        'bcc' => 'bcc',
        'reply_to' => 'reply_to',
        'envelope_recipients' => 'envelope_to',
    ];

    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table): void {
            $table->id();

            $table->foreignId('mailroom_message_id')
                ->constrained($this->messagesTable())
                ->cascadeOnDelete();

            // An AddressRole value: from, to, cc, bcc, reply_to,
            // envelope_from or envelope_to.
            $table->string('role', 16);

            // Order within the role, so "To: Rachel, Sam" reads back the way
            // it was written. A full integer, since a bulk send can name more
            // recipients than a small integer holds.
            $table->unsignedInteger('position');

            // Lowercased on the way in, so filtering and grouping by person
            // need no function call on the column and can use the index.
            $table->string('address');

            // As it appeared on this message. Kept per row rather than per
            // person, because the same address can carry different names.
            $table->string('name')->nullable();

            $table->unique(['mailroom_message_id', 'role', 'position']);
            // Covers the People ranking (grouped by address, filtered by role,
            // reading the message id and the name) without touching the table.
            $table->index(['address', 'role', 'mailroom_message_id', 'name']);
        });

        $this->copyAddressesOut();

        Schema::connection($this->getConnection())->table($this->messagesTable(), function (Blueprint $table): void {
            $table->dropColumn([...array_keys($this->jsonColumns), 'envelope_sender']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table($this->messagesTable(), function (Blueprint $table): void {
            foreach (array_keys($this->jsonColumns) as $column) {
                $table->json($column)->nullable();
            }

            $table->string('envelope_sender')->nullable();
        });

        $this->copyAddressesBack();

        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    protected function copyAddressesOut(): void
    {
        $this->db()->table($this->messagesTable())
            ->select(['id', ...array_keys($this->jsonColumns), 'envelope_sender'])
            ->orderBy('id')
            ->chunkById(500, function ($messages): void {
                $rows = [];

                foreach ($messages as $message) {
                    foreach ($this->jsonColumns as $column => $role) {
                        $addresses = json_decode((string) $message->{$column}, true);

                        foreach (is_array($addresses) ? array_values($addresses) : [] as $position => $address) {
                            if (! is_array($address) || blank($address['address'] ?? null)) {
                                continue;
                            }

                            $rows[] = $this->row($message->id, $role, $position, $address['address'], $address['name'] ?? null);
                        }
                    }

                    if (filled($message->envelope_sender)) {
                        $rows[] = $this->row($message->id, 'envelope_from', 0, $message->envelope_sender, null);
                    }
                }

                // Chunked again on the way in: SQLite caps bound parameters
                // per statement, and a batch of 500 messages can carry far
                // more addresses than that.
                foreach (array_chunk($rows, 100) as $batch) {
                    $this->db()->table($this->table())->insert($batch);
                }
            });
    }

    protected function copyAddressesBack(): void
    {
        $this->db()->table($this->messagesTable())
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($messages): void {
                $byMessage = $this->db()->table($this->table())
                    ->whereIn('mailroom_message_id', $messages->pluck('id'))
                    ->orderBy('position')
                    ->get()
                    ->groupBy('mailroom_message_id');

                foreach ($messages as $message) {
                    $addresses = $byMessage->get($message->id, collect());
                    $values = [];

                    foreach ($this->jsonColumns as $column => $role) {
                        $values[$column] = json_encode($addresses
                            ->where('role', $role)
                            ->map(fn ($row): array => ['address' => $row->address, 'name' => $row->name])
                            ->values()
                            ->all());
                    }

                    $values['envelope_sender'] = $addresses->firstWhere('role', 'envelope_from')?->address;

                    $this->db()->table($this->messagesTable())->where('id', $message->id)->update($values);
                }
            });
    }

    /**
     * @return array{mailroom_message_id: int, role: string, position: int, address: string, name: string|null}
     */
    protected function row(int $messageId, string $role, int $position, string $address, ?string $name): array
    {
        return [
            'mailroom_message_id' => $messageId,
            'role' => $role,
            'position' => $position,
            'address' => mb_strtolower($address),
            // SMTP's envelope has no names, so envelope rows carry none,
            // as they do when captured. A longer name is cut to the column,
            // which MySQL in strict mode and PostgreSQL would otherwise
            // refuse, failing the migration.
            'name' => filled($name) && ! str_starts_with($role, 'envelope_') ? mb_substr($name, 0, 255) : null,
        ];
    }

    protected function db(): Connection
    {
        return DB::connection($this->getConnection());
    }

    public function getConnection(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function table(): string
    {
        return config('mailroom.database.addresses_table', 'mailroom_addresses');
    }

    protected function messagesTable(): string
    {
        return config('mailroom.database.messages_table', 'mailroom_messages');
    }
};
