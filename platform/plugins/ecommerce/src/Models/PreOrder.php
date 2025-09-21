<?php

namespace Botble\Ecommerce\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'pre_order_start_date' => 'datetime',
        'pre_order_end_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'name' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        static::deleted(fn (PreOrder $preOrder) => $preOrder->products()->detach());
    }

    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'ec_pre_order_products', 'pre_order_id', 'product_id')
            ->withPivot(['price', 'max_quantity', 'pre_ordered', 'is_active']);
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
}
