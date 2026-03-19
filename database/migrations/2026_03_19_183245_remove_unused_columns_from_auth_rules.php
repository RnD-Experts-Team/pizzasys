<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveUnusedColumnsFromAuthRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auth_rules', function (Blueprint $table) {
            // Remove the unused columns
            $table->dropColumn([
                'store_all_access_roles_any',
                'store_all_access_permissions_any',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auth_rules', function (Blueprint $table) {
            // Add the columns back if needed
            $table->json('store_all_access_roles_any')->nullable();
            $table->json('store_all_access_permissions_any')->nullable();
        });
    }
}