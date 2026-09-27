@php
    $rows = [
        'Référence' => $appointment->reference,
        'Demandeur' => $appointment->fullName(),
        'Organisation' => $appointment->company,
        'Fonction' => $appointment->job_title,
        'E-mail' => $appointment->email,
        'Téléphone' => $appointment->phone,
        'Objet' => $appointment->subject,
        'Date' => $appointment->startsAt()->translatedFormat('l j F Y'),
        'Heure' => $appointment->time.' ('.$appointment->duration.' min)',
        'Personnes' => $appointment->attendees,
        'Accompagnants' => $appointment->companions,
        'Message' => $appointment->message,
    ];
@endphp
<table cellpadding="6" style="border-collapse:collapse;font-family:Segoe UI,Arial,sans-serif;font-size:14px">
    @foreach (array_filter($rows, 'filled') as $label => $value)
        <tr>
            <td style="color:#555;vertical-align:top"><strong>{{ $label }}</strong></td>
            <td>{!! nl2br(e($value)) !!}</td>
        </tr>
    @endforeach
</table>
