<?php

namespace Database\Factories;

use App\Models\ImportLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'filename' => "imports/test_{$this->faker->uuid()}.xlsx",
            'original_filename' => $this->faker->word() . '.xlsx',
            'status' => 'completed',
            'total_rows' => $total = $this->faker->numberBetween(10, 100),
            'successful_rows' => $success = $this->faker->numberBetween(1, $total),
            'skipped_rows' => $total - $success,
            'summary_json' => [],
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'Unexpected error during processing.',
        ]);
    }
}
