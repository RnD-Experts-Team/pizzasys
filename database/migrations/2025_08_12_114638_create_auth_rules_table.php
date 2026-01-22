<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_rules', function (Blueprint $table) {
            $table->id();

            // Existing core fields
            $table->string('service', 190)->index();
            $table->string('method', 10)->default('ANY')->index(); // GET/POST/.../ANY

            $table->string('path_dsl', 190)->nullable();     // friendly DSL path
            $table->string('path_regex', 300)->nullable();   // compiled regex
            $table->string('route_name', 190)->nullable()->index();

            $table->json('roles_any')->nullable();
            $table->json('permissions_any')->nullable();
            $table->json('permissions_all')->nullable();

            // ✅ NEW: Store scoping fields
            $table->string('store_scope_mode', 30)->default('none')->index();
            // none | scoped | all_stores

            $table->json('store_id_sources')->nullable();
            // Example:
            // { "path":["store_id"], "query":["store_id","store_ids"], "body":["store_id","filters.store_ids"] }

            $table->string('store_match_policy', 10)->default('all')->index();
            // all | any

            $table->boolean('store_allows_empty')->default(false);

            $table->json('store_all_access_roles_any')->nullable();
            $table->json('store_all_access_permissions_any')->nullable();

            // Existing control fields
            $table->boolean('is_active')->default(true)->index();
            $table->integer('priority')->default(0)->index();

            $table->timestamps();

            // Helpful compound indexes
            $table->index(['service', 'method', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_rules');
    }
};
