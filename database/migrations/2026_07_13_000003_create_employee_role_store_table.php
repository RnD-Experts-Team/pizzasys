<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_role_store', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['employee_id', 'role_id', 'store_id']);
            $table->index(['store_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_role_store');
    }
};
