<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_rules', function (Blueprint $table) {
            $table->id();

            // Which service this rule applies to (e.g., 'data', 'shiftsvc')
            $table->string('service');

            // HTTP method, uppercase (GET/POST/PUT/PATCH/DELETE). Use 'ANY' to match all methods.
            $table->string('method')->default('GET');

            // EITHER a user-friendly path pattern OR a route name (never both blank)
            // Path DSL examples: /orders, /orders/{id}, /orders/*, /orders/**, /orders/{id}/items/*
            $table->string('path_dsl')->nullable();

            // We also store a compiled regex derived from the DSL for fast matching
            $table->string('path_regex')->nullable();

            // Optional route_name exact match (if provided, it takes precedence over path)
            $table->string('route_name')->nullable();

            // Authorization requirements (JSON arrays)
            $table->json('roles_any')->nullable();
            $table->json('permissions_any')->nullable();
            $table->json('permissions_all')->nullable();

            // Active toggle
            $table->boolean('is_active')->default(true);

            // Priority (higher = matched first). Default 100. Tie-breaker: lowest id wins.
            $table->integer('priority')->default(100);

            $table->timestamps();

            $table->index(['service', 'method']);
            $table->index(['service', 'route_name']);
            $table->index(['service', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_rules');
    }
};
