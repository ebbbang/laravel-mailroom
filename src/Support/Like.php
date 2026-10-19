<?php

namespace Ebbbang\Mailroom\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A "contains" match that takes the term literally, so a search for "50%" or
 * "user_id" finds that text rather than treating % and _ as wildcards.
 *
 * The wildcards are escaped with a backslash, which MySQL and PostgreSQL
 * already treat as LIKE's escape character. SQLite and SQL Server have no
 * default, so on those the escape character is named in an ESCAPE clause.
 * "[" is escaped too, because SQL Server reads it as a character class.
 *
 * It ignores case. MySQL, SQL Server and SQLite's LIKE already do under
 * their default collations; PostgreSQL's does not, so there it is ILIKE.
 */
final class Like
{
    /**
     * Drivers whose LIKE has no escape character unless one is named.
     */
    private const NEEDS_ESCAPE_CLAUSE = ['sqlite', 'sqlsrv'];

    public static function contains(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        $pattern = '%'.addcslashes($term, '\\%_[').'%';
        $driver = $query->getConnection()->getDriverName();

        if (! in_array($driver, self::NEEDS_ESCAPE_CLAUSE, true)) {
            return $query->where($column, $driver === 'pgsql' ? 'ilike' : 'like', $pattern, $boolean);
        }

        return $query->whereRaw($query->getGrammar()->wrap($column)." like ? escape '\\'", [$pattern], $boolean);
    }
}
