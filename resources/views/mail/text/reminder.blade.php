{{-- The plain-text version, for mail apps that don't show HTML. Values are output raw:
     plain text isn't HTML, so escaping would show &#039; for an apostrophe. --}}
Hi {!! $firstName !!},

{!! $body !!}

Open Beach Ready: {!! config('app.url') !!}

@include('mail.text.footer')
