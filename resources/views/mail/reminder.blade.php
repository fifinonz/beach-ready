<x-mail::message :reason="$footerReason" :preferences-url="$preferencesUrl" :unsubscribe-url="$unsubscribeUrl" :postal-address="$postalAddress">
Hi {{ $firstName }},

{{ $body }}

<x-mail::button :url="config('app.url')">
Open Beach Ready
</x-mail::button>
</x-mail::message>
