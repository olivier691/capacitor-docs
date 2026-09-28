@extends('mail.layout')

@section('content')
    @php($when = $appointment->startsAt()->translatedFormat('l j F Y \à H:i'))
    <p>Bonjour {{ $appointment->fullName() }},</p>

    @switch($appointment->status)
        @case(\App\Enums\AppointmentStatus::Approved)
            <p>Votre rendez-vous est <strong>confirmé</strong> le <strong>{{ $when }}</strong>
                ({{ $appointment->duration }} min). Lieu : {{ config('appointments.location') }}.</p>
            <p>Merci de vous présenter à l'accueil muni d'une pièce d'identité et de votre référence
                <strong>{{ $appointment->reference }}</strong>.</p>
            @break
        @case(\App\Enums\AppointmentStatus::Cancelled)
            <p>Nous sommes au regret de vous informer que votre rendez-vous du {{ $when }} est <strong>annulé</strong>.</p>
            @break
        @default
            <p>Nous sommes au regret de vous informer que votre demande de rendez-vous du {{ $when }}
                ne peut pas être accordée.</p>
    @endswitch

    @if ($appointment->decision_note)
        <p><strong>{{ $appointment->status === \App\Enums\AppointmentStatus::Approved ? 'Note' : 'Motif' }} :</strong>
            {{ $appointment->decision_note }}</p>
    @endif
@endsection
