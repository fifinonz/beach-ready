<x-mail::subcopy>
{{ $footerReason }}<br>
[Manage email preferences]({{ $preferencesUrl }}) · [Unsubscribe]({{ $unsubscribeUrl }})
@if ($postalAddress)
<br>Beach Ready · {{ $postalAddress }}
@endif
</x-mail::subcopy>
