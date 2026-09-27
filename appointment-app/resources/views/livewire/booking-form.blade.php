<div class="mx-auto max-w-3xl">
    @if ($reference)
        <div class="card" role="status">
            <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 font-medium text-emerald-800">Votre demande a bien été envoyée.</div>
            <p>Référence : <strong>{{ $reference }}</strong></p>
            <p class="mt-2 text-slate-600">Elle a été transmise au PDG et à son secrétariat. Vous recevrez un e-mail dès qu'elle aura été traitée.</p>
            <button type="button" wire:click="startOver" class="btn btn-primary mt-6">Faire une autre demande</button>
        </div>
    @else
        <form wire:submit="submit" class="card space-y-8" novalidate>
            <p class="text-sm text-slate-600">Les champs marqués d'un <span class="required"></span> sont obligatoires.</p>

            @error('form')
                <div class="rounded-lg bg-red-50 px-4 py-3 text-red-800" role="alert">{{ $message }}</div>
            @enderror

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-semibold">Vos informations</legend>
                <div class="grid gap-4 sm:grid-cols-[8rem_1fr_1fr]">
                    <div>
                        <label for="title" class="field-label">Civilité</label>
                        <select id="title" wire:model="title" class="field-input">
                            <option value="">—</option>
                            @foreach ($titles as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="first_name" label="Prénom" required autocomplete="given-name" />
                    <x-field name="last_name" label="Nom" required autocomplete="family-name" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="company" label="Entreprise / organisation" autocomplete="organization" />
                    <x-field name="job_title" label="Fonction" autocomplete="organization-title" />
                    <x-field name="email" label="E-mail" type="email" required autocomplete="email" inputmode="email" />
                    <x-field name="phone" label="Téléphone" type="tel" required autocomplete="tel" inputmode="tel" />
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="mb-2 text-lg font-semibold">Le rendez-vous</legend>
                <x-field name="subject" label="Objet du rendez-vous" required maxlength="200" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="date" label="Date souhaitée" type="date" required :min="$today" />
                    <x-field name="time" label="Heure souhaitée" type="time" required step="900" />
                    <div>
                        <label for="duration" class="field-label required">Durée</label>
                        <select id="duration" wire:model="duration" class="field-input">
                            @foreach ($durations as $d)
                                <option value="{{ $d }}">{{ $d < 60 ? "$d min" : intdiv($d, 60).' h'.($d % 60 ? ' '.($d % 60) : '') }}</option>
                            @endforeach
                        </select>
                        @error('duration') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="attendees" label="Nombre de personnes (vous compris)" type="number" required
                             min="1" max="{{ config('appointments.max_attendees') }}" inputmode="numeric" live />
                    @if ($attendees > 1)
                        <div>
                            <label for="companions" class="field-label required">Nom des accompagnants</label>
                            <textarea id="companions" wire:model="companions" rows="2" maxlength="500" class="field-input"
                                      @error('companions') aria-invalid="true" @enderror></textarea>
                            @error('companions') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                <div>
                    <label for="message" class="field-label">Informations complémentaires</label>
                    <textarea id="message" wire:model="message" rows="3" maxlength="2000" class="field-input"></textarea>
                </div>
            </fieldset>

            <div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="consent" class="mt-1 size-5 shrink-0 accent-brand">
                    <span>J'accepte que ces informations soient transmises au PDG, à son entourage habilité et au service de sécurité pour l'organisation et le contrôle d'accès du rendez-vous.</span>
                </label>
                @error('consent') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary w-full sm:w-auto" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Envoyer la demande</span>
                    <span wire:loading wire:target="submit">Envoi en cours…</span>
                </button>
            </div>
        </form>
    @endif
</div>
