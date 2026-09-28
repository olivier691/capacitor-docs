<?php

namespace App\Console\Commands;

use App\Support\AccessControl;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'app:permissions';

    protected $description = 'Crée les permissions manquantes (App\Enums\Permission) et les rôles par défaut absents';

    public function handle(): int
    {
        AccessControl::sync();
        $this->info('Permissions et rôles par défaut à jour.');

        return self::SUCCESS;
    }
}
