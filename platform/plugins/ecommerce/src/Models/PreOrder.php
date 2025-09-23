<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreOrder extends BaseModel
{
    protected $table = 'ec_pre_orders';

    protected $fillable = [
        'name',
        'pre_order_start_date',
        'pre_order_end_date',
        'expected_delivery_date',
        'status',
        'description',
        'custom_pre_order_message',
        'deposit_amount',
        'deposit_percentage',
        'requires_deposit',
        'allow_full_payment',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'pre_order_start_date' => 'datetime',
        'pre_order_end_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'name' => SafeContent::class,
        'custom_pre_order_message' => 'string',
        'deposit_amount' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'requires_deposit' => 'boolean',
        'allow_full_payment' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (PreOrder $preOrder) => $preOrder->products()->detach());
    }

    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'ec_pre_order_products', 'pre_order_id', 'product_id')
            ->withPivot(['price', 'max_quantity', 'pre_ordered', 'is_active', 'deposit_amount', 'deposit_percentage']);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PreOrderPayment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BaseStatusEnum::PUBLISHED)
            ->where('pre_order_start_date', '<=', Carbon::now())
            ->where('pre_order_end_date', '>=', Carbon::now());
    }

    public function scopeNotStarted(Builder $query): Builder
    {
        return $query->where('pre_order_start_date', '>', Carbon::now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('pre_order_end_date', '<', Carbon::now());
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(function (): bool {
            $now = Carbon::now();
            return $this->status === BaseStatusEnum::PUBLISHED &&
                $this->pre_order_start_date->lte($now) &&
                $this->pre_order_end_date->gte($now);
        });
    }

    protected function isNotStarted(): Attribute
    {
        return Attribute::get(fn (): bool => $this->pre_order_start_date->gt(Carbon::now()));
    }

    protected function isExpired(): Attribute
    {
        return Attribute::get(fn (): bool => $this->pre_order_end_date->lt(Carbon::now()));
    }

    protected function preOrderCountLeftLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->pivot) {
                return null;
            }

            return $this->pivot->pre_ordered . '/' . $this->pivot->max_quantity;
        })->shouldCache();
    }

    protected function preOrderCountLeftPercent(): Attribute
    {
        return Attribute::get(function (): float {
            if (! $this->pivot || ! $this->pivot->max_quantity) {
                return 0;
            }

            return ($this->pivot->pre_ordered / $this->pivot->max_quantity) * 100;
        })->shouldCache();
    }

    public function calculateDepositAmount(Product $product, int $quantity = 1): float
    {
        $productPrice = $this->products()->where('product_id', $product->id)->first()?->pivot?->price ?? $product->price;
        $totalAmount = $productPrice * $quantity;

        // Check product-specific deposit first
        $productPivot = $this->products()->where('product_id', $product->id)->first()?->pivot;
        if ($productPivot?->deposit_amount) {
            return $productPivot->deposit_amount * $quantity;
        }
        if ($productPivot?->deposit_percentage) {
            return ($totalAmount * $productPivot->deposit_percentage) / 100;
        }

        // Fall back to pre-order level deposit
        if ($this->deposit_amount) {
            return $this->deposit_amount * $quantity;
        }
        if ($this->deposit_percentage) {
            return ($totalAmount * $this->deposit_percentage) / 100;
        }

        return $totalAmount; // Full payment if no deposit configured
    }

    public function requiresDeposit(): bool
    {
        return $this->requires_deposit;
    }

    public function allowsFullPayment(): bool
    {
        return $this->allow_full_payment;
    }
}
