<?php

use App\Livewire\Auth\Login;
use App\Livewire\BookingForm;
use App\Livewire\Direction\Appointments;
use App\Livewire\Security\Visits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Formulaire public de demande de rendez-vous.
Route::livewire('/', BookingForm::class)->name('booking');

Route::livewire('/connexion', Login::class)->middleware('guest')->name('login');

Route::middleware('auth')->group(function () {
    Route::livewire('/direction', Appointments::class)->middleware('role:direction')->name('direction');
    Route::livewire('/securite', Visits::class)->middleware('role:security')->name('security');

    Route::post('/deconnexion', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
