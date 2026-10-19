@php
    use Ebbbang\Mailroom\Support\Avatar;
    use Ebbbang\Mailroom\Support\Highlight;

    $avatar = Avatar::for($entry->address, $entry->name);
    $active = $person !== null && mb_strtolower($person) === $entry->address;
@endphp

<a
    href="{{ route('mailroom.index', ['person' => $entry->address]) }}"
    class="mr-person"
    title="{{ $entry->address }} &middot; {{ $entry->summary() }}"
    @if ($active) aria-current="page" @endif
>
    <span class="mr-avatar mr-avatar-sm mr-tone-{{ $avatar->tone }}" aria-hidden="true">{{ $avatar->initials }}</span>
    <span class="mr-person-text">
        <span class="mr-person-address">{{ Highlight::mark($entry->address, $peopleSearch ?? null) }}</span>
        <span class="mr-person-summary">{{ $entry->summary() }}</span>
    </span>
</a>
