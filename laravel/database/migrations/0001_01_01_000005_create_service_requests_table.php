<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('service_type_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('requested_date');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->text('processing_notes')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('service_type_id')->references('id')->on('service_types')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');

            // Prevent duplicate requests: per tenant, same student + service type + date
            $table->unique(['tenant_id', 'student_id', 'service_type_id', 'requested_date'], 'uniq_request');

            $table->index(['tenant_id', 'status'], 'idx_1');
            $table->index(['tenant_id', 'created_at'], 'idx_2');
            $table->index(['tenant_id', 'student_id'], 'idx_3');
            $table->index(['tenant_id', 'assigned_to'], 'idx_4');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
