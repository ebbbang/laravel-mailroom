{{--
    The sidebar's people: the most frequent, or the matches for a search, and
    a line saying how many more there are. Rendered into the page and, on its
    own, by the people endpoint the search box fetches from.
--}}
@if ($people->isNotEmpty())
    <div class="mr-people">
        @foreach ($people as $entry)
            @include('mailroom::partials.person', ['entry' => $entry])
        @endforeach
    </div>

    <p class="mr-people-more">
        @if (blank($peopleSearch))
            @if ($peopleTotal > $people->count())
                {{ number_format($peopleTotal - $people->count()) }} more &middot; type to find them
            @endif
        @elseif ($peopleTotal > $people->count())
            {{ $people->count() }} of {{ number_format($peopleTotal) }} matches &middot; keep typing
        @else
            {{ $peopleTotal }} {{ Str::plural('match', $peopleTotal) }}
        @endif
    </p>
@elseif (filled($peopleSearch))
    <p class="mr-people-empty">
        No one matches &ldquo;{{ $peopleSearch }}&rdquo;.
        <span>Every address on every message is searched.</span>
    </p>
@endif
