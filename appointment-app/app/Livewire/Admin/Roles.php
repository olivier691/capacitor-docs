<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Support\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.app', ['heading' => 'Administration'])]
#[Title('Rôles et permissions')]
class Roles extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(): void
    {
        Gate::authorize(Permission::ManageRoles->value);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->with('permissions')->withCount('users')->orderBy('name')->get();
    }

    public function create(): void
    {
        Gate::authorize(Permission::ManageRoles->value);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize(Permission::ManageRoles->value);
        $role = Role::findOrFail($id);

        $this->resetForm();
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('name')->all();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize(Permission::ManageRoles->value);
        $role = $this->editingId ? Role::findOrFail($this->editingId) : null;
        $isAdmin = $role?->name === AccessControl::ADMIN_ROLE;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:60',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->editingId)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);

        if ($isAdmin && $data['name'] !== AccessControl::ADMIN_ROLE) {
            $this->addError('name', 'Le rôle Administrateur ne peut pas être renommé.');

            return;
        }

        if ($isAdmin) {
            // Le rôle Administrateur garde toujours l'accès à l'administration.
            $data['permissions'] = array_values(array_unique(array_merge($data['permissions'], array_map(
                fn (Permission $p) => $p->value, AccessControl::PROTECTED_ADMIN_PERMISSIONS,
            ))));
        }

        try {
            DB::transaction(function () use ($role, $data) {
                $role ??= Role::create(['name' => $data['name'], 'guard_name' => 'web']);
                $role->update(['name' => $data['name']]);
                $role->syncPermissions($data['permissions']);
                $this->ensureAnAdministratorRemains();
            });
        } catch (RuntimeException $e) {
            $this->addError('permissions', $e->getMessage());

            return;
        }

        session()->flash('success', $this->editingId ? 'Rôle mis à jour.' : 'Rôle créé.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize(Permission::ManageRoles->value);
        $role = Role::findOrFail($id);

        if ($role->name === AccessControl::ADMIN_ROLE) {
            session()->flash('error', 'Le rôle Administrateur ne peut pas être supprimé.');

            return;
        }

        try {
            DB::transaction(function () use ($role) {
                $role->delete();
                $this->ensureAnAdministratorRemains();
            });
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('success', "Rôle « {$role->name} » supprimé.");
        unset($this->roles);
    }

    private function ensureAnAdministratorRemains(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (AccessControl::activeUserManagers() === 0) {
            throw new RuntimeException('Au moins un compte actif doit conserver la gestion des utilisateurs.');
        }
    }

    private function resetForm(): void
    {
        $this->reset('showForm', 'editingId', 'name', 'permissions');
        $this->resetValidation();
        unset($this->roles);
    }

    public function render()
    {
        return view('livewire.admin.roles', [
            'groups' => Permission::grouped(),
            'protected' => array_map(fn (Permission $p) => $p->value, AccessControl::PROTECTED_ADMIN_PERMISSIONS),
            'adminRole' => AccessControl::ADMIN_ROLE,
        ]);
    }
}
