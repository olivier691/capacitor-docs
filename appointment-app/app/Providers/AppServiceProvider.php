<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Services\MicrosoftGraph\GraphClient;
use App\Services\MicrosoftGraph\GraphMailTransport;
use App\Services\OutlookCalendar;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GraphClient::class, fn () => GraphClient::fromConfig());

        $this->app->singleton(OutlookCalendar::class, fn ($app) => new OutlookCalendar(
            $app->make(GraphClient::class),
            config('services.microsoft_graph.calendar_user'),
        ));
    }

    public function boot(): void
    {
        Mail::extend('microsoft-graph', fn () => new GraphMailTransport($this->app->make(GraphClient::class)));

        // Réapplique ces contrôles à chaque action Livewire, pas seulement au chargement de la page.
        // Les droits eux-mêmes sont les permissions spatie/laravel-permission (voir App\Enums\Permission).
        Livewire::addPersistentMiddleware([EnsureAccountIsActive::class, PermissionMiddleware::class]);
    }
}
