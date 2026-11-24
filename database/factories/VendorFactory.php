<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['company_name' => $name, 'name_key' => mb_strtolower($name), 'primary_email' => fake()->unique()->safeEmail(), 'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], 'time_zone' => 'America/New_York', 'is_active' => false];
    }
}
