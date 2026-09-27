<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Connexion')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        $key = Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.');

            return null;
        }

        if (! Auth::attempt(['email' => Str::lower($this->email), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 15 * 60);
            $this->reset('password');
            $this->addError('email', 'Identifiant ou mot de passe incorrect.');

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route(Auth::user()->role->homeRoute()));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
