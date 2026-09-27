<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app', ['heading' => 'Administration'])]
#[Title('Utilisateurs')]
class Users extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;

    /** Compte en cours de modification (null = création). */
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @var list<string> Noms des rôles attribués. */
    public array $roles = [];

    public bool $is_active = true;

    public function mount(): void
    {
        Gate::authorize(Permission::ManageUsers->value);
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('roles')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    public function create(): void
    {
        Gate::authorize(Permission::ManageUsers->value);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize(Permission::ManageUsers->value);
        $user = User::findOrFail($id);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roles = $user->getRoleNames()->all();
        $this->is_active = $user->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize(Permission::ManageUsers->value);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:12'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId === Auth::id() && ! $this->is_active) {
            $this->addError('is_active', 'Vous ne pouvez pas désactiver votre propre compte.');

            return;
        }

        try {
            $this->persist(function () use ($data) {
                $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
                $user->fill([
                    'name' => $data['name'],
                    'email' => Str::lower($data['email']),
                    'is_active' => $data['is_active'],
                ]);
                if (filled($data['password'])) {
                    $user->password = $data['password'];
                }
                $user->save();
                $user->syncRoles($data['roles']);
            });
        } catch (RuntimeException $e) {
            $this->addError('roles', $e->getMessage());

            return;
        }

        session()->flash('success', $this->editingId ? 'Compte mis à jour.' : 'Compte créé.');
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize(Permission::ManageUsers->value);
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            session()->flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');

            return;
        }

        try {
            $this->persist(fn () => $user->forceFill(['is_active' => ! $user->is_active])->save());
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', $user->is_active ? "Compte de {$user->name} réactivé." : "Compte de {$user->name} désactivé.");
    }

    /** Applique une modification en garantissant qu'au moins un administrateur actif subsiste. */
    private function persist(callable $change): void
    {
        DB::transaction(function () use ($change) {
            $change();

            if (AccessControl::activeUserManagers() === 0) {
                throw new RuntimeException('Au moins un compte actif doit conserver la gestion des utilisateurs.');
            }
        });
        unset($this->users);
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingId', 'name', 'email', 'password', 'roles', 'is_active');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.users');
    }
}
