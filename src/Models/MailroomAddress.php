<?php

namespace Ebbbang\Mailroom\Models;

use Ebbbang\Mailroom\Support\AddressRole;
use Ebbbang\Mailroom\Support\Like;
use Ebbbang\Mailroom\Support\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One address on one captured message, in one role.
 *
 * These rows are the only record of who a message was from and to. Rows
 * rather than a JSON array on the message, because SQLite cannot filter a
 * JSON array by membership, and a search over encoded JSON matches its key
 * names and misses any name stored as \u escapes.
 *
 * @property int $id
 * @property int $mailroom_message_id
 * @property AddressRole $role
 * @property int $position
 * @property string $address
 * @property string|null $name
 */
class MailroomAddress extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('mailroom.database.addresses_table', 'mailroom_addresses');
    }

    public function getConnectionName(): ?string
    {
        return config('mailroom.database.connection');
    }

    protected function casts(): array
    {
        return [
            'role' => AddressRole::class,
            'position' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailroomMessage::class, 'mailroom_message_id');
    }

    /**
     * @param  array<int, static>  $models
     */
    public function newCollection(array $models = []): AddressCollection
    {
        return new AddressCollection($models);
    }

    /**
     * "Name <address>" when there is a name, the bare address when not.
     */
    public function display(): string
    {
        return filled($this->name) ? sprintf('%s <%s>', $this->name, $this->address) : $this->address;
    }

    /**
     * Every address that sent or received mail, most messages first.
     *
     * One grouped query over the (address, message) index. A person on the
     * same message twice, in To and Cc say, is still one message, which is
     * why the total counts distinct messages while the per-role counts do
     * not.
     *
     * The name is MAX(name): an address can carry different names on
     * different messages, and MAX picks one deterministically while skipping
     * the rows that had none.
     *
     * A search picks the addresses, and the counts then cover every row of
     * each. Filtering the rows themselves would count only the messages that
     * carried the name searched for, while the person's list shows them all.
     *
     * @return Collection<int, Person>
     */
    public static function people(int $limit, ?string $search = null): Collection
    {
        $roles = AddressRole::people();

        $counts = collect($roles)->map(fn (AddressRole $role): string => sprintf(
            "sum(case when role = '%s' then 1 else 0 end) as %s_count",
            $role->value,
            $role->value,
        ))->implode(', ');

        return static::query()
            ->whereIn('role', $roles)
            ->when(filled(trim((string) $search)), fn (Builder $query): Builder => $query->whereIn(
                'address',
                static::query()->select('address')->whereIn('role', $roles)->matching($search),
            ))
            ->selectRaw('address, max(name) as name, count(distinct mailroom_message_id) as total, '.$counts)
            ->groupBy('address')
            ->orderByDesc('total')
            ->orderBy('address')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): Person => new Person(
                address: $row->address,
                name: $row->name,
                total: (int) $row->total,
                roles: collect($roles)
                    ->mapWithKeys(fn (AddressRole $role): array => [$role->value => (int) $row->{$role->value.'_count'}])
                    ->filter()
                    ->all(),
            ));
    }

    public static function peopleCount(?string $search = null): int
    {
        return static::query()
            ->whereIn('role', AddressRole::people())
            ->matching($search)
            ->distinct()
            ->count('address');
    }

    /**
     * Rows whose address or display name contains the term, ignoring case.
     */
    protected function scopeMatching(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        // Like ignores case on every driver, but SQLite only for ASCII.
        // Addresses are stored lowercased, so folding the term as well
        // matches an address with accented letters typed in capitals.
        return $query->where(function (Builder $match) use ($search): void {
            Like::contains($match, 'address', mb_strtolower($search));
            Like::contains($match, 'name', $search, 'or');
        });
    }
}
