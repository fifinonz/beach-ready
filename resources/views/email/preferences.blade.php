@extends('layouts.page')

@section('title', 'Email preferences')

@section('content')
    <h1>Email preferences</h1>
    <p>Choose what Beach Ready emails you about at {{ $user->email }}.</p>

    @if (session('saved'))
        <p class="notice" role="status">Saved.</p>
    @endif

    @unless ($user->hasConfirmedEmail())
        <p class="notice">Nothing is emailed until you confirm your address. Turning a topic on sends a confirmation email if you haven't had one.</p>
    @endunless

    <form method="post" action="{{ $formUrl }}">
        @csrf
        <div class="card">
            @foreach ($topics as $topic)
                <label class="topic">
                    <span>{{ $topic->label() }}<small>{{ $topic->description() }}</small></span>
                    <input type="checkbox" role="switch" name="{{ $topic->value }}" value="1" @checked($user->{$topic->column()})>
                </label>
            @endforeach
        </div>
        <button class="button" type="submit">Save preferences</button>
    </form>

    <p style="margin-top: 2rem"><a href="{{ $unsubscribeUrl }}">Unsubscribe from all emails</a></p>
@endsection
