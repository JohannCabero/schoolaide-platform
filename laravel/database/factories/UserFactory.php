<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function asAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $user->tenant_id]
            );
            $user->assignRole($role);
        });
    }

    public function asStaff(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $user->tenant_id]
            );
            $user->assignRole($role);
        });
    }

    public function asStudent(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $user->tenant_id]
            );
            $user->assignRole($role);
        });
    }
}
