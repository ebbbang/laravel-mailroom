{{--
    The list state as hidden fields, for forms that act on the list and have
    to bring the reader back to the same place. Mailroom::LIST_STATE decides
    what that is; pass `except` for any key the form sets itself.
--}}
@foreach (\Illuminate\Support\Arr::except($state, $except ?? []) as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
@endforeach
