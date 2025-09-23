<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->boolean('is_preorder_enabled')->default(false)->after('is_featured');
        });

        Schema::table('ec_pre_orders', function (Blueprint $table) {
            $table->text('custom_pre_order_message')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('is_preorder_enabled');
        });

        Schema::table('ec_pre_orders', function (Blueprint $table) {
            $table->dropColumn('custom_pre_order_message');
        });
    }
}; 