<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Models\BaseModel;
use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'cancellation_reason',
        'cancellation_reason_description',
        'refunded_amount',
        'status_updated_at',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'payment_type' => PreOrderPaymentTypeEnum::class,
        'payment_data' => 'array',
        'payment_due_date' => 'datetime',
        'status_updated_at' => 'datetime',
        'customer_name' => SafeContent::class,
        'customer_email' => SafeContent::class,
        'cancellation_reason' => SafeContent::class,
        'cancellation_reason_description' => SafeContent::class,
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

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PreOrderPaymentStatusHistory::class, 'pre_order_payment_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the related order for this pre-order payment
     */
    public function order()
    {
        // Find order through OrderProduct that matches this payment
        $query = \Botble\Ecommerce\Models\OrderProduct::query()
            ->where('product_id', $this->product_id)
            ->when($this->customer_id, function ($q) {
                $q->whereHas('order', function ($query) {
                    $query->where('user_id', $this->customer_id);
                });
            })
            ->when(!$this->customer_id && $this->customer_email, function ($q) {
                $q->whereHas('order', function ($query) {
                    $query->whereHas('address', function ($addrQuery) {
                        $addrQuery->where('email', $this->customer_email);
                    })->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('email', $this->customer_email);
                    });
                });
            });

        // Only add date filter if created_at is not null
        if ($this->created_at) {
            $query->whereDate('created_at', $this->created_at->format('Y-m-d'));
        }

        $orderProduct = $query->with('order')
            ->orderByDesc('id')
            ->first();

        return $orderProduct?->order;
    }

    /**
     * Get the related order product for this pre-order payment
     */
    public function orderProduct()
    {
        $query = \Botble\Ecommerce\Models\OrderProduct::query()
            ->where('product_id', $this->product_id)
            ->when($this->customer_id, function ($q) {
                $q->whereHas('order', function ($query) {
                    $query->where('user_id', $this->customer_id);
                });
            })
            ->when(!$this->customer_id && $this->customer_email, function ($q) {
                $q->whereHas('order', function ($query) {
                    $query->whereHas('address', function ($addrQuery) {
                        $addrQuery->where('email', $this->customer_email);
                    })->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('email', $this->customer_email);
                    });
                });
            });

        // Only add date filter if created_at is not null
        if ($this->created_at) {
            $query->whereDate('created_at', $this->created_at->format('Y-m-d'));
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Calculate balance including tax
     */
    protected function balanceWithTax(): Attribute
    {
        return Attribute::get(function () {
            $orderProduct = $this->orderProduct();
            $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $this->quantity) : 0;
            return $this->total_amount - $this->paid_amount + $taxAmount;
        });
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