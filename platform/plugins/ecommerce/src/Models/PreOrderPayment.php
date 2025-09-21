<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Models\BaseModel;
use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreOrderPayment extends BaseModel
{
    protected $table = 'ec_pre_order_payments';

    protected $fillable = [
        'pre_order_id',
        'product_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'quantity',
        'product_price',
        'total_amount',
        'payment_type',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_data',
        'payment_due_date',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'payment_type' => PreOrderPaymentTypeEnum::class,
        'payment_data' => 'array',
        'payment_due_date' => 'datetime',
        'customer_name' => SafeContent::class,
        'customer_email' => SafeContent::class,
    ];

    public function preOrder(): BelongsTo
    {
        return $this->belongsTo(PreOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function isPaid(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_status === 'paid');
    }

    protected function isPending(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_status === 'pending');
    }

    protected function isPartiallyPaid(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_status === 'partially_paid');
    }

    protected function isFailed(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_status === 'failed');
    }

    protected function isDeposit(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_type === PreOrderPaymentTypeEnum::DEPOSIT);
    }

    protected function isFullPayment(): Attribute
    {
        return Attribute::get(fn (): bool => $this->payment_type === PreOrderPaymentTypeEnum::FULL_PAYMENT);
    }

    protected function paymentProgress(): Attribute
    {
        return Attribute::get(function (): float {
            if ($this->total_amount <= 0) {
                return 0;
            }
            
            return min(100, ($this->paid_amount / $this->total_amount) * 100);
        });
    }
} 