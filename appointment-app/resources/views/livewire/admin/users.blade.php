<div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <h1 class="text-xl font-semibold">Comptes utilisateurs</h1>
        <div class="flex w-full gap-2 sm:w-auto">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher un nom ou un e-mail"
                   aria-label="Rechercher" class="field-input sm:w-72">
            <button type="button" wire:click="create" class="btn btn-primary shrink-0">Nouveau compte</button>
        </div>
    </div>

    @foreach (['success' => 'bg-emerald-50 text-emerald-800', 'error' => 'bg-red-50 text-red-800'] as $type => $classes)
        @if (session($type))
            <div class="mb-4 rounded-lg px-4 py-3 {{ $classes }}" role="status">{{ session($type) }}</div>
        @endif
    @endforeach

    <ul class="space-y-3">
        @forelse ($this->users as $user)
            <li wire:key="user-{{ $user->id }}" @class(['card flex flex-wrap items-center gap-3', 'opacity-60' => ! $user->is_active])>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">
                        {{ $user->name }}
                        @if ($user->id === auth()->id()) <span class="text-sm font-normal text-slate-500">(vous)</span> @endif
                        @unless ($user->is_active) <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs">Désactivé</span> @endunless
                    </p>
                    <p class="truncate text-sm text-slate-600">{{ $user->email }}</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @forelse ($user->roles as $role)
                            <span class="rounded-full bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand">{{ $role->name }}</span>
                        @empty
                            <span class="text-xs text-amber-700">Aucun rôle : ce compte n'a accès à rien.</span>
                        @endforelse
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="edit({{ $user->id }})" class="btn btn-secondary">Modifier</button>
                    @if ($user->id !== auth()->id())
                        <button type="button" wire:click="toggleActive({{ $user->id }})"
                                wire:confirm="{{ $user->is_active ? 'Désactiver ce compte ? La personne ne pourra plus se connecter.' : 'Réactiver ce compte ?' }}"
                                class="btn btn-secondary">{{ $user->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                    @endif
                </div>
            </li>
        @empty
            <li class="card text-center text-slate-500">Aucun compte trouvé.</li>
        @endforelse
    </ul>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-end justify-center overflow-y-auto bg-black/50 p-4 sm:items-center" wire:keydown.escape.window="cancel">
            <form wire:submit="save" class="card w-full max-w-lg space-y-4" role="dialog" aria-modal="true" aria-labelledby="user-form-title">
                <h2 id="user-form-title" class="text-lg font-semibold">{{ $editingId ? 'Modifier le compte' : 'Nouveau compte' }}</h2>

                <x-field name="name" label="Nom affiché" required />
                <x-field name="email" label="Adresse e-mail (identifiant)" type="email" required autocomplete="off" />
                <div>
                    <x-field name="password" label="{{ $editingId ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe' }}"
                             type="password" :required="! $editingId" autocomplete="new-password" />
                    <p class="mt-1 text-xs text-slate-500">12 caractères minimum.</p>
                </div>

                <fieldset>
                    <legend class="field-label">Rôles</legend>
                    <div class="space-y-2">
                        @foreach ($this->availableRoles as $role)
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3" wire:key="role-{{ $role->id }}">
                                <input type="checkbox" wire:model="roles" value="{{ $role->name }}" class="mt-1 size-5 shrink-0 accent-brand">
                                <span>
                                    <span class="font-medium">{{ $role->name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $role->permissions->map(fn ($p) => \App\Enums\Permission::labelFor($p->name))->implode(' · ') ?: 'Aucune permission' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles') <p class="field-error">{{ $message }}</p> @enderror
                    @error('roles.*') <p class="field-error">{{ $message }}</p> @enderror
                </fieldset>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="is_active" class="size-5 accent-brand"> Compte actif
                </label>
                @error('is_active') <p class="field-error">{{ $message }}</p> @enderror

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer</button>
                </div>
            </form>
        </div>
    @endif
</div>
