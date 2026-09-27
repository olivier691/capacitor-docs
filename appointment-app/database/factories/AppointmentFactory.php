<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'M.',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => '+225 07 00 00 00 00',
            'subject' => fake()->sentence(4),
            'date' => today()->addDay()->toDateString(),
            'time' => '10:00',
            'duration' => 30,
            'attendees' => 1,
        ];
    }
}
