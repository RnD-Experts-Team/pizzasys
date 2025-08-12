<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // 'data', 'shiftsvc', etc.
            $table->string('token_hash', 128);     // sha256 hex of the service token
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // null => never expires
            $table->string('notes', 512)->nullable();
            $table->timestamps();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('use_count')->default(0);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('service_clients');
    }
};
