@extends('layouts.page')

@section('title', 'Confirm your email')

@section('content')
    <h1>One click and you're set, {{ $firstName }}</h1>
    <p>Confirm this is your email address and we'll start sending the reminders you asked for. Nothing is sent until you do.</p>
    <form method="post" action="{{ $formUrl }}">
        @csrf
        <button class="button" type="submit">Yes, send me reminders</button>
    </form>
@endsection
