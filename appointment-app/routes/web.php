<?php

use App\Enums\Permission;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\BookingForm;
use App\Livewire\Direction\Appointments;
use App\Livewire\Security\Visits;
use App\Support\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Formulaire public de demande de rendez-vous.
Route::livewire('/', BookingForm::class)->name('booking');

Route::livewire('/connexion', Login::class)->middleware('guest')->name('login');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/accueil', fn (Request $request) => redirect()->route(AccessControl::homeRouteFor($request->user())))
        ->name('home');

    Route::livewire('/direction', Appointments::class)
        ->middleware('permission:'.Permission::ViewAppointments->value)->name('direction');
    Route::livewire('/securite', Visits::class)
        ->middleware('permission:'.Permission::ViewVisits->value)->name('security');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/utilisateurs', Users::class)
            ->middleware('permission:'.Permission::ManageUsers->value)->name('users');
        Route::livewire('/roles', Roles::class)
            ->middleware('permission:'.Permission::ManageRoles->value)->name('roles');
    });

    Route::post('/deconnexion', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
