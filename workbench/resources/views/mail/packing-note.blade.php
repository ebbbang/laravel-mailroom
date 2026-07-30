<x-mail::message>
# Packing note

Order **{{ $orderNumber }}** has been packed and is ready for collection.

<x-mail::table>
| Item             | Quantity | Price  |
|:-----------------|:--------:|-------:|
| Widget, size 2   | 2        | £39.98 |
| Gizmo            | 1        | £9.02  |
</x-mail::table>

<x-mail::button :url="'https://example.test/orders/'.$orderNumber">
View the order
</x-mail::button>

<x-mail::panel>
Someone must be home to sign for this delivery.
</x-mail::panel>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
