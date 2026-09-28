@extends('mail.layout')

@section('content')
    <p>Une nouvelle demande de rendez-vous avec le PDG a été déposée.</p>
    @include('mail.partials.appointment-details')
    <p style="margin-top:20px">
        <a href="{{ route('direction') }}" style="background:#0f4c81;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block">
            Valider ou refuser la demande
        </a>
    </p>
@endsection
