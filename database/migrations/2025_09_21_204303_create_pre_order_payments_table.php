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
        Schema::create('ec_pre_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_order_id')->constrained('ec_pre_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('ec_products')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('ec_customers')->onDelete('cascade');
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('product_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_type', 20); // 'deposit' or 'full_payment'
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('payment_status', 20)->default('pending'); // pending, paid, partially_paid, failed
            $table->string('payment_method')->nullable(); // paystack, bank_transfer, etc.
            $table->string('payment_reference')->nullable();
            $table->json('payment_data')->nullable(); // Store additional payment gateway data
            $table->timestamp('payment_due_date')->nullable();
            $table->timestamps();

            $table->index(['pre_order_id', 'customer_id']);
            $table->index(['payment_status']);
            $table->index(['payment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ec_pre_order_payments');
    }
};
