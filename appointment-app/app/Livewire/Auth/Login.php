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

        $credentials = ['email' => Str::lower($this->email), 'password' => $this->password, 'is_active' => true];
        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($key, 15 * 60);
            $this->reset('password');
            $this->addError('email', 'Identifiant ou mot de passe incorrect.');

            return null;
        }

        RateLimiter::clear($key);
        // (Le middleware « active » refuse ensuite un compte sans aucun accès.)
        session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
