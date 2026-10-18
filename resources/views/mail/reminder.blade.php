<x-mail::message>
Hi {{ $firstName }},

{{ $body }}

<x-mail::button :url="config('app.url')">
Open Beach Ready
</x-mail::button>

@include('mail.partials.footer')
</x-mail::message>
