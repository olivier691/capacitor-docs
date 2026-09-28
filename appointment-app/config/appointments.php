<?php

$list = fn (?string $value) => array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value))));

return [

    // Nom affiché dans les e-mails.
    'organisation' => env('APPOINTMENTS_ORGANISATION', env('APP_NAME', 'Direction Générale')),

    // Lieu indiqué dans l'invitation Outlook et la confirmation envoyée au visiteur.
    'location' => env('APPOINTMENTS_LOCATION', 'Bureau du PDG'),

    // PDG et entourage habilité : destinataires de chaque nouvelle demande.
    'notify' => $list(env('APPOINTMENTS_NOTIFY_EMAILS')),

    // Durées proposées dans le formulaire (minutes).
    'durations' => [15, 30, 45, 60, 90, 120],

    'max_attendees' => 20,

];
