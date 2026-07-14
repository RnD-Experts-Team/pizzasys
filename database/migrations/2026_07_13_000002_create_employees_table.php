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

            // Store NUMBER string from hiring (e.g. "03795-00001"), not an FK.
            // Store-scoped role assignments use employee_role_store.store_id → stores.id instead.
            $table->string('store_id', 50)->index();

            $table->boolean('active')->default(true)->index();

            $table->string('password');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
