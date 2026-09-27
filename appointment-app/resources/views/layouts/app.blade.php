<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Rendez-vous' }} — {{ config('appointments.organisation') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="bg-brand text-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ auth()->check() ? route(auth()->user()->role->homeRoute()) : route('booking') }}" class="text-lg font-semibold">
                {{ $heading ?? 'Rendez-vous avec le PDG' }}
            </a>
            @auth
                <div class="flex items-center gap-3 text-sm">
                    <span>{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="underline underline-offset-2">Déconnexion</button>
                    </form>
                </div>
            @else
                @unless (request()->routeIs('login'))
                    <a href="{{ route('login') }}" class="rounded-lg border border-white/70 px-3 py-2 text-sm font-medium hover:bg-white/10">Espace personnel</a>
                @endunless
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
        {{ $slot }}
    </main>
</body>
</html>
