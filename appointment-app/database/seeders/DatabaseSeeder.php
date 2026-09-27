<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Données de démonstration (développement uniquement). */
    public function run(): void
    {
        User::factory()->direction()->create([
            'name' => 'Assistante du PDG',
            'email' => 'direction@example.com',
            'password' => 'direction-demo',
        ]);

        User::factory()->security()->create([
            'name' => 'Poste de garde',
            'email' => 'securite@example.com',
            'password' => 'securite-demo',
        ]);

        Appointment::factory()->count(3)->create();
    }
}
