<div class="mx-auto max-w-3xl" wire:poll.60s.visible>
    <div class="mb-4 flex flex-wrap items-end gap-2">
        <button type="button" wire:click="shiftDay(-1)" class="btn btn-secondary" aria-label="Jour précédent">‹</button>
        <div class="min-w-40 flex-1">
            <label for="date" class="field-label">Date</label>
            <input id="date" type="date" wire:model.live="date" class="field-input">
        </div>
        <button type="button" wire:click="shiftDay(1)" class="btn btn-secondary" aria-label="Jour suivant">›</button>
        @unless ($isToday)
            <button type="button" wire:click="goToday" class="btn btn-secondary">Aujourd'hui</button>
        @endunless
    </div>

    @php($arrived = $this->visits->whereNotNull('arrived_at')->count())
    <h1 class="text-xl font-semibold">{{ ucfirst(\Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y')) }}</h1>
    <div class="my-3 flex flex-wrap gap-2 text-sm font-semibold">
        <span class="rounded-full border border-slate-300 bg-white px-3 py-1">{{ $this->visits->count() }} rendez-vous</span>
        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">{{ $arrived }} venu(s)</span>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-800">{{ $this->visits->count() - $arrived }} attendu(s)</span>
    </div>

    @if (session('error'))
        <div class="mb-3 rounded-lg bg-red-50 px-4 py-3 text-red-800" role="alert">{{ session('error') }}</div>
    @endif

    <p class="mb-4 text-sm text-slate-600">Consultation seule. Cochez « Venu » lorsque le visiteur s'est présenté (le jour même uniquement).</p>

    <ul class="space-y-3">
        @forelse ($this->visits as $v)
            <li wire:key="visit-{{ $v->id }}"
                @class(['flex items-center gap-4 rounded-xl border p-4', 'border-emerald-600 bg-emerald-50' => $v->arrived_at, 'border-slate-200 bg-white' => ! $v->arrived_at])>
                <div class="w-14 shrink-0 text-lg font-bold">{{ $v->time }}</div>
                <div class="min-w-0 flex-1 text-sm">
                    <p class="text-base font-semibold">{{ $v->fullName() }}</p>
                    <p class="text-slate-600">{{ collect([$v->job_title, $v->company])->filter()->implode(' · ') ?: '—' }}</p>
                    <p class="text-slate-600">{{ $v->attendees }} personne(s) · {{ $v->duration }} min · {{ $v->reference }}</p>
                    @if ($v->companions)
                        <p class="text-slate-600">Accompagnants : {{ $v->companions }}</p>
                    @endif
                    @if ($v->arrived_at)
                        <p class="text-emerald-800">Arrivé à {{ $v->arrived_at->format('H:i') }} (pointé par {{ $v->arrivalRecordedBy?->name }})</p>
                    @endif
                </div>
                <label class="flex shrink-0 cursor-pointer flex-col items-center gap-1 text-xs font-medium">
                    <input type="checkbox" class="size-8 cursor-pointer accent-emerald-700 disabled:cursor-not-allowed"
                           wire:click="toggleArrival({{ $v->id }})" wire:loading.attr="disabled"
                           @checked($v->arrived_at) @disabled(! $isToday || ! $canCheckIn)
                           aria-label="Présence de {{ $v->fullName() }}">
                    Venu
                </label>
            </li>
        @empty
            <li class="card text-center text-slate-500">Aucun rendez-vous validé pour cette date.</li>
        @endforelse
    </ul>
</div>
