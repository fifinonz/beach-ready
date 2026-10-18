@extends('layouts.page')

@section('title', 'Unsubscribed')

@section('content')
    <h1>You're unsubscribed</h1>
    <p>Beach Ready won't email you any more. Changed your mind? <a href="{{ $preferencesUrl }}">Manage email preferences</a>.</p>
@endsection
