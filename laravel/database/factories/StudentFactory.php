<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_number' => $this->faker->unique()->numerify('20##-####'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'),
            'status' => 'active',
            'program' => $this->faker->randomElement([
                'BS Computer Science',
                'BS Information Technology',
                'BS Business Administration',
                'BS Nursing',
            ]),
            'year_level' => $this->faker->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function forTenant(\App\Models\Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
