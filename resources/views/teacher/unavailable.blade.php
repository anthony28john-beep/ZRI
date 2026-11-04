@extends('layouts.app')
@section('title', 'Classes unavailable | Teacher Portal')
@section('content')
@include('teacher.partials.navbar')
<main class="tp-shell"><section class="tp-panel tp-empty"><h1>Classes temporarily unavailable</h1><p>We could not load your class assignments. Please try again shortly.</p><a class="button" href="{{ url()->current() }}">Try Again</a></section></main>
@endsection
