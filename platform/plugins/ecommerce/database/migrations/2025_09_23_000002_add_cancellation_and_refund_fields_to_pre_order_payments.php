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
        Schema::table('ec_pre_order_payments', function (Blueprint $table) {
            $table->string('cancellation_reason', 255)->nullable()->after('payment_due_date');
            $table->text('cancellation_reason_description')->nullable()->after('cancellation_reason');
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('cancellation_reason_description');
            $table->timestamp('status_updated_at')->nullable()->after('refunded_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_pre_order_payments', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_reason',
                'cancellation_reason_description',
                'refunded_amount',
                'status_updated_at',
            ]);
        });
    }
};
