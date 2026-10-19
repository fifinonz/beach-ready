{{-- The plain-text version, for mail apps that don't show HTML. Values are output raw:
     plain text isn't HTML, so escaping would show &#039; for an apostrophe. --}}
One click and you're set, {!! $firstName !!}

You asked for fasting reminders. Confirm this is your email address and we'll start sending them. Nothing is sent until you do.

Yes, send me reminders:
{!! $confirmUrl !!}

This link works for {!! $linkValidHours !!} hours. Didn't ask for this? Ignore this email and we won't email you again.

@include('mail.text.footer')
