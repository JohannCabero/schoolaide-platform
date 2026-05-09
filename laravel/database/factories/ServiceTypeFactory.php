<?php

namespace Database\Factories;

use App\Models\ServiceType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    public function definition(): array
    {
        $types = [
            ['name' => 'Transcript of Records', 'code' => 'TRANSCRIPT'],
            ['name' => 'Certificate of Enrollment', 'code' => 'ENROLLMENT'],
            ['name' => 'Good Moral Certificate', 'code' => 'GOOD_MORAL'],
            ['name' => 'Diploma', 'code' => 'DIPLOMA'],
            ['name' => 'Honorable Dismissal', 'code' => 'DISMISSAL'],
        ];
        $type = $this->faker->unique()->randomElement($types);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $type['name'],
            'code' => $type['code'],
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
