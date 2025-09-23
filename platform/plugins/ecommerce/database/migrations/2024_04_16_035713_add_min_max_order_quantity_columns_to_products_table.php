<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ec_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('ec_products', 'minimum_order_quantity')) {
                $table->integer('minimum_order_quantity')->default(0)->nullable()->unsigned();
            }

            if (! Schema::hasColumn('ec_products', 'maximum_order_quantity')) {
                $table->integer('maximum_order_quantity')->default(0)->nullable()->unsigned();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table): void {
            if (Schema::hasColumn('ec_products', 'minimum_order_quantity')) {
                $table->dropColumn('minimum_order_quantity');
            }

            if (Schema::hasColumn('ec_products', 'maximum_order_quantity')) {
                $table->dropColumn('maximum_order_quantity');
            }
        });
    }
};
