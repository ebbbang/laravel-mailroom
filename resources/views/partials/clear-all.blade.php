{{-- Rendered twice: in the sidebar, and in the list footer where a phone
     keeps it. CSS shows one; both post to the same place. --}}
<form
    method="POST"
    action="{{ route('mailroom.clear') }}"
    class="mr-inline-form {{ $class }}"
    data-mr-confirm="mr-confirm-clear"
>
    @csrf
    @method('DELETE')
    <button type="submit" class="mr-btn mr-btn-ghost mr-btn-danger mr-btn-sm" title="Clear all">
        <svg class="mr-clear-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        </svg>
        <span class="mr-clear-text">Clear all</span>
    </button>
</form>
