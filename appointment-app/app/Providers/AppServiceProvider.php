<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\MicrosoftGraph\GraphClient;
use App\Services\MicrosoftGraph\GraphMailTransport;
use App\Services\OutlookCalendar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

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

        // Le PDG et son entourage gèrent les demandes ; la sécurité consulte et pointe les arrivées.
        Gate::define('manage-appointments', fn (User $user) => $user->hasRole(Role::Direction));
        Gate::define('record-arrival', fn (User $user) => $user->hasRole(Role::Security));
    }
}
