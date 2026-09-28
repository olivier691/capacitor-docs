<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Rôles et permissions</h1>
        <button type="button" wire:click="create" class="btn btn-primary">Nouveau rôle</button>
    </div>

    @foreach (['success' => 'bg-emerald-50 text-emerald-800', 'error' => 'bg-red-50 text-red-800'] as $type => $classes)
        @if (session($type))
            <div class="mb-4 rounded-lg px-4 py-3 {{ $classes }}" role="status">{{ session($type) }}</div>
        @endif
    @endforeach

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($this->roles as $role)
            <article class="card" wire:key="role-{{ $role->id }}">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $role->name }}</h2>
                    <span class="text-sm text-slate-500">{{ $role->users_count }} compte(s)</span>
                </div>
                <ul class="mt-2 list-inside list-disc text-sm text-slate-700">
                    @forelse ($role->permissions as $permission)
                        <li>{{ \App\Enums\Permission::labelFor($permission->name) }}</li>
                    @empty
                        <li class="list-none text-slate-500">Aucune permission</li>
                    @endforelse
                </ul>
                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="edit({{ $role->id }})" class="btn btn-secondary">Modifier</button>
                    @if ($role->name !== $adminRole)
                        <button type="button" wire:click="delete({{ $role->id }})"
                                wire:confirm="Supprimer le rôle « {{ $role->name }} » ? Les {{ $role->users_count }} compte(s) concerné(s) perdront ses permissions."
                                class="btn btn-secondary text-red-700">Supprimer</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-end justify-center overflow-y-auto bg-black/50 p-4 sm:items-center" wire:keydown.escape.window="cancel">
            <form wire:submit="save" class="card w-full max-w-lg space-y-4" role="dialog" aria-modal="true" aria-labelledby="role-form-title">
                <h2 id="role-form-title" class="text-lg font-semibold">{{ $editingId ? 'Modifier le rôle' : 'Nouveau rôle' }}</h2>

                <x-field name="name" label="Nom du rôle" required :readonly="$name === $adminRole && $editingId" />

                @foreach ($groups as $group => $items)
                    <fieldset>
                        <legend class="field-label">{{ $group }}</legend>
                        <div class="space-y-2">
                            @foreach ($items as $permission)
                                @php($locked = $name === $adminRole && $editingId && in_array($permission->value, $protected, true))
                                <label class="flex items-start gap-3" wire:key="perm-{{ $permission->value }}">
                                    <input type="checkbox" wire:model="permissions" value="{{ $permission->value }}"
                                           class="mt-0.5 size-5 shrink-0 accent-brand" @disabled($locked)>
                                    <span>{{ $permission->label() }}
                                        @if ($locked) <span class="block text-xs text-slate-500">Toujours accordée au rôle Administrateur.</span> @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                @error('permissions') <p class="field-error">{{ $message }}</p> @enderror
                @error('permissions.*') <p class="field-error">{{ $message }}</p> @enderror

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer</button>
                </div>
            </form>
        </div>
    @endif
</div>
