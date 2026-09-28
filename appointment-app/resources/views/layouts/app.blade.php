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
            <a href="{{ auth()->check() ? route('home') : route('booking') }}" class="text-lg font-semibold">
                {{ $heading ?? 'Rendez-vous avec le PDG' }}
            </a>
            @auth
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span>{{ auth()->user()->name }} · {{ auth()->user()->getRoleNames()->implode(', ') }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cursor-pointer underline underline-offset-2">Déconnexion</button>
                    </form>
                </div>
            @else
                @unless (request()->routeIs('login'))
                    <a href="{{ route('login') }}" class="rounded-lg border border-white/70 px-3 py-2 text-sm font-medium hover:bg-white/10">Espace personnel</a>
                @endunless
            @endauth
        </div>
        @auth
            @php($spaces = \App\Support\AccessControl::spacesFor(auth()->user()))
            @if (count($spaces) > 1)
                <nav class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 pb-2 text-sm" aria-label="Espaces">
                    @foreach ($spaces as $space)
                        <a href="{{ route($space['route']) }}"
                           @class(['shrink-0 rounded-md px-3 py-1.5', 'bg-white text-brand font-semibold' => request()->routeIs($space['route']), 'hover:bg-white/10' => ! request()->routeIs($space['route'])])>
                            {{ $space['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif
        @endauth
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
        {{ $slot }}
    </main>
</body>
</html>
