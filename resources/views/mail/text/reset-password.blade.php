{{-- The plain-text version, for mail apps that don't show HTML. Values are output raw:
     plain text isn't HTML, so escaping would show &#039; for an apostrophe. --}}
Reset your password, {!! $firstName !!}

Someone asked to reset the password for your Beach Ready account. If that was you, choose a new one:
{!! $resetUrl !!}

This link works once, for {!! $linkValidMinutes !!} minutes. Didn't ask for this? Ignore this email: your password won't change.

@include('mail.text.footer')
