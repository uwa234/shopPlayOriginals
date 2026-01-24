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
        Schema::create('ec_pre_order_payment_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pre_order_payment_id');
            $table->string('status', 50);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 50)->nullable();
            $table->timestamps();

            $table->foreign('pre_order_payment_id', 'fk_pre_order_payment_status_history')
                ->references('id')
                ->on('ec_pre_order_payments')
                ->onDelete('cascade');

            $table->index(['pre_order_payment_id', 'created_at'], 'idx_pre_order_payment_status_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_pre_order_payment_status_history');
    }
};
