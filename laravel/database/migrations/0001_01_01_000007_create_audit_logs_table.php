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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->enum('action', ['created', 'updated', 'deleted', 'restored', 'approved', 'rejected', 'login', 'logout']);
            $table->json('old_values')->nullable()->comment('State before change');
            $table->json('new_values')->nullable()->comment('State after change');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'auditable_type', 'auditable_id'], 'idx_audit_entity');
            $table->index(['tenant_id', 'user_id'], 'idx_audit_user');
            $table->index(['tenant_id', 'action'], 'idx_audit_action');
            $table->index(['tenant_id', 'created_at'], 'idx_audit_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
