@extends('layouts.page')

@section('title', 'Email confirmed')

@section('content')
    <h1>Email confirmed. Reminders are on.</h1>
    <p>Thanks, {{ $firstName }}. We'll email the reminders you've chosen, and nothing else unless you turn it on.</p>
    <p><a class="button" href="{{ url('/') }}">Open Beach Ready</a></p>
@endsection
