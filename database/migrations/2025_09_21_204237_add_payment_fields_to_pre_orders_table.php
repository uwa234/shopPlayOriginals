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
        Schema::table('ec_pre_orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 15, 2)->nullable()->after('description');
            $table->decimal('deposit_percentage', 5, 2)->nullable()->after('deposit_amount');
            $table->boolean('requires_deposit')->default(false)->after('deposit_percentage');
            $table->boolean('allow_full_payment')->default(true)->after('requires_deposit');
        });

        Schema::table('ec_pre_order_products', function (Blueprint $table) {
            $table->decimal('deposit_amount', 15, 2)->nullable()->after('price');
            $table->decimal('deposit_percentage', 5, 2)->nullable()->after('deposit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_pre_orders', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_percentage', 
                'requires_deposit',
                'allow_full_payment'
            ]);
        });

        Schema::table('ec_pre_order_products', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'deposit_percentage'
            ]);
        });
    }
};
