@extends('mail.layout')

@section('content')
    <p>Bonjour {{ $appointment->fullName() }},</p>
    <p>Votre demande de rendez-vous a bien été reçue. Elle sera étudiée et vous recevrez une réponse par e-mail.</p>
    @include('mail.partials.appointment-details')
@endsection
