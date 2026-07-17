<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Hiring-sourced store membership. One row per store an employee belongs
        // to. Populated ONLY by hiring.v1.employee.* event handlers — never by
        // admins. An employee can belong to many stores.
        Schema::create('employee_stores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Store number string from hiring (e.g. "03795-00001"). Maps to
            // pizzasys stores.store_id (the store code) for authorization.
            $table->string('store_number', 50)->index();

            // Raw hiring status for this store: hired / rehired / OJE / resigned / terminated.
            $table->string('status', 30)->nullable();

            // Derived: true iff status is an active one (hired / rehired / OJE).
            $table->boolean('active')->default(false)->index();

            $table->date('effective_date')->nullable();

            $table->timestamps();

            // One current row per (employee, store).
            $table->unique(['employee_id', 'store_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_stores');
    }
};
