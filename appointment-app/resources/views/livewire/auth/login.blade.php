<div class="mx-auto max-w-md">
    <form wire:submit="login" class="card space-y-4" novalidate>
        <h1 class="text-xl font-semibold">Espace direction et sécurité</h1>
        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="username" autocapitalize="none" />
        <x-field name="password" label="Mot de passe" type="password" autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="remember" class="size-4 accent-brand"> Rester connecté
        </label>
        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">Se connecter</button>
    </form>
</div>
