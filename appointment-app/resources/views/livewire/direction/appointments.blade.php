<div wire:poll.60s.visible>
    <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" aria-label="Filtrer par statut">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')"
                    @class(['btn shrink-0 border', 'border-brand bg-brand text-white' => $status === $key, 'border-slate-300 bg-white' => $status !== $key])
                    aria-pressed="{{ $status === $key ? 'true' : 'false' }}">
                {{ $label }}
                @if ($key !== 'all' && ($this->counts[$key] ?? 0))
                    <span class="ml-2 rounded-full bg-black/10 px-2 text-xs">{{ $this->counts[$key] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    @foreach (['success' => 'bg-emerald-50 text-emerald-800', 'error' => 'bg-red-50 text-red-800'] as $type => $classes)
        @if (session($type))
            <div class="mb-4 rounded-lg px-4 py-3 {{ $classes }}" role="status">{{ session($type) }}</div>
        @endif
    @endforeach

    <div class="space-y-4">
        @forelse ($this->appointments as $a)
            <article class="card" wire:key="rdv-{{ $a->id }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold">
                        {{ ucfirst($a->startsAt()->translatedFormat('l j F Y')) }} · {{ $a->time }}
                        <span class="font-normal text-slate-500">({{ $a->duration }} min)</span>
                    </h2>
                    <span class="rounded-full px-3 py-0.5 text-sm font-semibold {{ $a->status->badgeClasses() }}">{{ $a->status->label() }}</span>
                </div>

                @if ($a->status === \App\Enums\AppointmentStatus::Pending && ($cardOverlaps = $a->overlappingApproved())->isNotEmpty())
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        Chevauche un rendez-vous déjà validé : {{ $cardOverlaps->map(fn ($o) => $o->time.' — '.$o->fullName())->implode(', ') }}
                    </p>
                @endif

                <dl class="mt-3 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                    @foreach ([
                        'Demandeur' => $a->fullName(),
                        'Organisation' => $a->company,
                        'Fonction' => $a->job_title,
                        'Objet' => $a->subject,
                        'E-mail' => $a->email,
                        'Téléphone' => $a->phone,
                        'Personnes' => $a->attendees,
                        'Accompagnants' => $a->companions,
                        'Message' => $a->message,
                        'Référence' => $a->reference,
                        'Reçu le' => $a->created_at->translatedFormat('d/m/Y H:i'),
                        'Décision' => $a->decidedBy ? $a->decidedBy->name.', le '.$a->decided_at->translatedFormat('d/m/Y H:i') : null,
                        'Note' => $a->decision_note,
                        'Arrivée' => $a->status === \App\Enums\AppointmentStatus::Approved
                            ? ($a->arrived_at ? 'Présent — pointé à '.$a->arrived_at->format('H:i').' par '.$a->arrivalRecordedBy?->name : 'Non pointée')
                            : null,
                    ] as $label => $value)
                        @if (filled($value))
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="break-words whitespace-pre-line">{{ $value }}</dd>
                        @endif
                    @endforeach
                </dl>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($a->status === \App\Enums\AppointmentStatus::Pending && auth()->user()->can('appointments.decide'))
                        <button type="button" wire:click="open('approve', {{ $a->id }})" class="btn btn-success flex-1 sm:flex-none">Valider</button>
                        <button type="button" wire:click="open('reject', {{ $a->id }})" class="btn btn-danger flex-1 sm:flex-none">Refuser</button>
                    @elseif ($a->status === \App\Enums\AppointmentStatus::Approved && auth()->user()->can('appointments.cancel'))
                        <button type="button" wire:click="open('cancel', {{ $a->id }})" class="btn btn-secondary">Annuler le rendez-vous</button>
                    @endif
                </div>
            </article>
        @empty
            <p class="card text-center text-slate-500">Aucun rendez-vous.</p>
        @endforelse
    </div>

    @if ($action && $this->current)
        <div class="fixed inset-0 z-10 flex items-end justify-center bg-black/50 p-4 sm:items-center" wire:keydown.escape.window="close">
            <form wire:submit="confirm" class="card w-full max-w-lg space-y-4" role="dialog" aria-modal="true" aria-labelledby="dialog-title">
                <h2 id="dialog-title" class="text-lg font-semibold">
                    {{ ['approve' => 'Valider le rendez-vous', 'reject' => 'Refuser la demande', 'cancel' => 'Annuler le rendez-vous'][$action] }}
                </h2>
                <p class="text-sm text-slate-600">
                    {{ $this->current->fullName() }} —
                    @if ($action === 'approve')
                        le rendez-vous sera ajouté à l'agenda Outlook du PDG et le demandeur recevra une confirmation. Vous pouvez ajuster le créneau.
                    @elseif ($action === 'cancel')
                        le rendez-vous sera retiré de l'agenda Outlook du PDG.
                    @else
                        {{ $this->current->subject }}
                    @endif
                </p>

                @if ($action === 'approve')
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-field name="slotDate" label="Date" type="date" />
                        <x-field name="slotTime" label="Heure" type="time" step="900" />
                        <div>
                            <label for="slotDuration" class="field-label">Durée</label>
                            <select id="slotDuration" wire:model="slotDuration" class="field-input">
                                @foreach ($durations as $d)
                                    <option value="{{ $d }}">{{ $d }} min</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div>
                    <label for="note" class="field-label">{{ $action === 'approve' ? 'Note pour le demandeur (facultatif)' : 'Motif (facultatif)' }}</label>
                    <textarea id="note" wire:model="note" rows="3" maxlength="500" class="field-input"></textarea>
                </div>

                @if ($action !== 'approve')
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="notify" class="size-4 accent-brand"> Informer le demandeur par e-mail
                    </label>
                @endif

                @if ($overlaps)
                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                        <p class="font-semibold">Ce créneau chevauche {{ count($overlaps) }} rendez-vous déjà validé(s) :</p>
                        <ul class="list-inside list-disc">@foreach ($overlaps as $o)<li>{{ $o }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="close" class="btn btn-secondary">Fermer</button>
                    @if ($overlaps)
                        <button type="button" wire:click="confirm(true)" class="btn btn-success" wire:loading.attr="disabled">Valider quand même</button>
                    @else
                        <button type="submit" @class(['btn', 'btn-success' => $action === 'approve', 'btn-danger' => $action !== 'approve']) wire:loading.attr="disabled">
                            {{ ['approve' => 'Valider', 'reject' => 'Refuser', 'cancel' => 'Annuler le rendez-vous'][$action] }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif
</div>
