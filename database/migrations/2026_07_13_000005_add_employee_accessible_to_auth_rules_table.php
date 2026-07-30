<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auth_rules', function (Blueprint $table) {
            // Additive flag: employee tokens may only match rules where this is true.
            // Users are unaffected — their evaluation ignores this column.
            $table->boolean('employee_accessible')->default(false)->index()->after('store_allows_empty');
        });
    }

    public function down(): void
    {
        Schema::table('auth_rules', function (Blueprint $table) {
            $table->dropColumn('employee_accessible');
        });
    }
};
