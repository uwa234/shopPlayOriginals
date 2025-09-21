<?php

namespace Botble\Ecommerce\Services;

use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Enums\PreOrderStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PreOrderService
{
    /**
     * Check if a product is available for pre-order
     */
    public function isProductAvailableForPreOrder(Product $product, int $quantity = 1): bool
    {
        $preOrder = $this->getActivePreOrderForProduct($product);
        
        if (!$preOrder) {
            return false;
        }

        return $this->checkQuantityAvailability($preOrder, $product, $quantity);
    }

    /**
     * Get active pre-order for a product
     */
    public function getActivePreOrderForProduct(Product $product): ?PreOrder
    {
        return PreOrder::query()
            ->active()
            ->whereHas('products', function ($query) use ($product) {
                $query->where('product_id', $product->id)
                      ->where('is_active', true);
            })
            ->first();
    }

    /**
     * Check quantity availability for pre-order
     */
    public function checkQuantityAvailability(PreOrder $preOrder, Product $product, int $quantity): bool
    {
        $pivot = $preOrder->products()->where('product_id', $product->id)->first()?->pivot;
        
        if (!$pivot) {
            return false;
        }

        // If max_quantity is null, unlimited pre-orders allowed
        if ($pivot->max_quantity === null) {
            return true;
        }

        $availableQuantity = $pivot->max_quantity - $pivot->pre_ordered;
        
        return $availableQuantity >= $quantity;
    }

    /**
     * Get available quantity for pre-order
     */
    public function getAvailableQuantity(PreOrder $preOrder, Product $product): ?int
    {
        $pivot = $preOrder->products()->where('product_id', $product->id)->first()?->pivot;
        
        if (!$pivot) {
            return 0;
        }

        // If max_quantity is null, return null (unlimited)
        if ($pivot->max_quantity === null) {
            return null;
        }

        return max(0, $pivot->max_quantity - $pivot->pre_ordered);
    }

    /**
     * Reserve pre-order quantity
     */
    public function reservePreOrderQuantity(PreOrder $preOrder, Product $product, int $quantity): bool
    {
        if (!$this->checkQuantityAvailability($preOrder, $product, $quantity)) {
            return false;
        }

        $preOrder->products()->updateExistingPivot($product->id, [
            'pre_ordered' => \DB::raw('pre_ordered + ' . $quantity)
        ]);

        return true;
    }

    /**
     * Release pre-order quantity (for cancelled orders)
     */
    public function releasePreOrderQuantity(PreOrder $preOrder, Product $product, int $quantity): bool
    {
        $pivot = $preOrder->products()->where('product_id', $product->id)->first()?->pivot;
        
        if (!$pivot) {
            return false;
        }

        $newQuantity = max(0, $pivot->pre_ordered - $quantity);
        
        $preOrder->products()->updateExistingPivot($product->id, [
            'pre_ordered' => $newQuantity
        ]);

        return true;
    }

    /**
     * Get pre-order progress information
     */
    public function getPreOrderProgress(PreOrder $preOrder, Product $product): array
    {
        $pivot = $preOrder->products()->where('product_id', $product->id)->first()?->pivot;
        
        if (!$pivot) {
            return [
                'pre_ordered' => 0,
                'max_quantity' => 0,
                'available' => 0,
                'progress_percent' => 0,
                'is_unlimited' => false,
            ];
        }

        $isUnlimited = $pivot->max_quantity === null;
        $available = $isUnlimited ? null : max(0, $pivot->max_quantity - $pivot->pre_ordered);
        $progressPercent = $isUnlimited ? 0 : (($pivot->pre_ordered / $pivot->max_quantity) * 100);

        return [
            'pre_ordered' => $pivot->pre_ordered,
            'max_quantity' => $pivot->max_quantity,
            'available' => $available,
            'progress_percent' => round($progressPercent, 2),
            'is_unlimited' => $isUnlimited,
        ];
    }

    /**
     * Check if pre-order is nearly sold out (90% or more)
     */
    public function isNearlySoldOut(PreOrder $preOrder, Product $product, float $threshold = 0.9): bool
    {
        $progress = $this->getPreOrderProgress($preOrder, $product);
        
        if ($progress['is_unlimited']) {
            return false;
        }

        return ($progress['progress_percent'] / 100) >= $threshold;
    }

    /**
     * Get pre-order status for display
     */
    public function getPreOrderStatus(PreOrder $preOrder): string
    {
        $now = Carbon::now();
        
        if ($preOrder->pre_order_start_date->gt($now)) {
            return 'not_started';
        }
        
        if ($preOrder->pre_order_end_date->lt($now)) {
            return 'expired';
        }
        
        return 'active';
    }

    /**
     * Get time remaining for pre-order
     */
    public function getTimeRemaining(PreOrder $preOrder): array
    {
        $now = Carbon::now();
        $endDate = $preOrder->pre_order_end_date;
        
        if ($endDate->lt($now)) {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0,
                'total_seconds' => 0,
                'expired' => true,
            ];
        }
        
        $diff = $now->diff($endDate);
        
        return [
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'total_seconds' => $endDate->timestamp - $now->timestamp,
            'expired' => false,
        ];
    }
} 