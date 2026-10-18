<x-mail::message>
# One click and you're set, {{ $firstName }}

You asked for fasting reminders. Confirm this is your email address and we'll start sending them. Nothing is sent until you do.

<x-mail::button :url="$confirmUrl">
Yes, send me reminders
</x-mail::button>

This link works for {{ $linkValidHours }} hours. Didn't ask for this? Ignore this email and we won't email you again.

@include('mail.partials.footer')
</x-mail::message>
