<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->string('id')->primary(); // user-chosen unique string
            $table->string('name')->unique();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_role_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id', 'store_id']);
            $table->index(['store_id', 'role_id']);
        });

        Schema::create('role_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('higher_role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('lower_role_id')->constrained('roles')->onDelete('cascade');
            $table->string('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['higher_role_id', 'lower_role_id', 'store_id']);
            $table->index(['store_id', 'higher_role_id']);
        });

        Schema::create('user_store_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->string('session_token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_store_sessions');
        Schema::dropIfExists('role_hierarchy');
        Schema::dropIfExists('user_role_store');
        Schema::dropIfExists('stores');
    }
};
