@extends('layouts.page')

@section('title', 'Unsubscribe')

@section('content')
    <h1>Unsubscribe from all emails?</h1>
    <p>You won't get fasting reminders, the weekly summary or product news. You can turn any of them back on later.</p>
    <form method="post" action="{{ $formUrl }}">
        <button class="button" type="submit">Unsubscribe</button>
    </form>
@endsection
