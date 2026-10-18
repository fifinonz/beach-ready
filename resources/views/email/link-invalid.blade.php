@extends('layouts.page')

@section('title', 'Link no longer works')

@section('content')
    <h1>This link no longer works</h1>
    <p>Links in our emails stop working after a while, or if they've been changed. Nothing has been updated.</p>
    <p>You can ask for a new confirmation email from the Reminders screen in Beach Ready.</p>
    <p><a class="button" href="{{ url('/') }}">Open Beach Ready</a></p>
@endsection
