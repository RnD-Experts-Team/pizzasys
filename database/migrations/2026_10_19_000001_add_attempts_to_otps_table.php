<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            // Wrong guesses against this code. At Otp::MAX_ATTEMPTS it is marked
            // used, so changing IP does not buy more guesses.
            $table->unsignedTinyInteger('attempts')->default(0)->after('used');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
