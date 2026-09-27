<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Crée les permissions de l'application et les rôles par défaut (Administrateur, Direction, Sécurité). */
    public function up(): void
    {
        AccessControl::sync();
    }

    public function down(): void
    {
        // Les tables sont supprimées par la migration de spatie/laravel-permission.
    }
};
