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
        Schema::create('ec_pre_orders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('pre_order_start_date');
            $table->dateTime('pre_order_end_date');
            $table->dateTime('expected_delivery_date');
            $table->string('status', 60)->default('published');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ec_pre_order_products', function (Blueprint $table) {
            $table->foreignId('pre_order_id');
            $table->foreignId('product_id');
            $table->double('price')->unsigned()->nullable();
            $table->integer('max_quantity')->unsigned()->nullable();
            $table->integer('pre_ordered')->unsigned()->default(0);
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_pre_order_products');
        Schema::dropIfExists('ec_pre_orders');
    }
};
