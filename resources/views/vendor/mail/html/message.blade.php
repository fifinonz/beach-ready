{{--
    Laravel's mail message, laid out like 08-confirmation-email.png: the
    Beach Ready mark at the top of the card, and under the card why the
    person is getting the email, the preference center and unsubscribe
    links, and the postal address. Every email passes these in; see
    BeachReadyMail::footer(). Account emails (AccountMail) have no
    preference center or unsubscribe links.
--}}
@props(['reason', 'preferencesUrl' => null, 'unsubscribeUrl' => null, 'postalAddress' => null])
<x-mail::layout>
<x-slot:header>
<tr>
<td class="header"></td>
</tr>
</x-slot:header>

<table class="brand" role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td><img src="{{ asset('icons/icon-192.png') }}" width="40" height="40" alt="" class="brand-icon"></td>
<td class="brand-name">Beach Ready</td>
</tr>
</table>

{!! $slot !!}

<x-slot:footer>
<x-mail::footer>
{{ $reason }}
@if ($preferencesUrl)
<br>[Manage email preferences]({{ $preferencesUrl }}) · [Unsubscribe]({{ $unsubscribeUrl }})
@endif
@if ($postalAddress)
<br>Beach Ready · {{ $postalAddress }}
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
