<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            // Hiring-system employee id — NOT auto-incrementing.
            $table->unsignedBigInteger('id')->primary();

            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);

            // Store memberships live in employee_stores (one row per store),
            // populated only by hiring events. `active` is derived: true iff the
            // employee has at least one store membership with an active status.
            $table->boolean('active')->default(false)->index();

            $table->string('password');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
