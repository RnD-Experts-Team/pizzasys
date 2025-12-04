<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // stable device identifier from mobile (preferred)
            $table->string('device_id')->nullable()->index();

            $table->string('platform')->nullable();     // ios, android, web
            $table->string('model')->nullable();        // device model
            $table->string('os_version')->nullable();   // e.g. 17.2, 14
            $table->string('app_version')->nullable();  // app build version

            $table->string('fcm_token', 500)->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();

            // if device_id exists, prevent duplicates per user
            $table->unique(['user_id', 'device_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
