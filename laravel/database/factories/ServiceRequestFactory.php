<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'student_id' => Student::factory()->forTenant($tenant),
            'service_type_id' => ServiceType::factory()->forTenant($tenant),
            'status' => 'pending',
            'requested_date' => $this->faker->unique()->dateTimeBetween('now', '+365 days')->format('Y-m-d'),
            'remarks' => $this->faker->optional()->sentence(),
            'version' => 1,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'processed_by' => null, 'processed_at' => null]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => 'approved',
            'processing_notes' => 'Approved.',
            'processed_at' => now(),
            'version' => 2,
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => 'rejected',
            'processing_notes' => 'Does not meet requirements.',
            'processed_at' => now(),
            'version' => 2,
        ]);
    }
}
