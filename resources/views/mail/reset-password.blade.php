<x-mail::message :reason="$footerReason" :postal-address="$postalAddress">
# Reset your password, {{ $firstName }}

Someone asked to reset the password for your Beach Ready account. If that was you, choose a new one.

<x-mail::button :url="$resetUrl">
Choose a new password
</x-mail::button>

This link works once, for {{ $linkValidMinutes }} minutes. Didn't ask for this? Ignore this email: your password won't change.
</x-mail::message>
