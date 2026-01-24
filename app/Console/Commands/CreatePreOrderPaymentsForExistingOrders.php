<?php

namespace App\Console\Commands;

use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Botble\Ecommerce\Services\PreOrderService;
use Illuminate\Console\Command;

class CreatePreOrderPaymentsForExistingOrders extends Command
{
    protected $signature = 'preorder:create-payments-for-orders {--order-id= : Specific order ID to process}';

    protected $description = 'Create PreOrderPayment records for existing orders that have pre-order products';

    public function handle(): int
    {
        $orderId = $this->option('order-id');

        $query = OrderProduct::query()
            ->whereHas('product', function ($q) {
                $q->where('is_preorder_enabled', true);
            })
            ->with(['product', 'order.user', 'order.address']);

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        $orderProducts = $query->get();

        if ($orderProducts->isEmpty()) {
            $this->info('No pre-order products found in orders.');
            return 0;
        }

        $this->info("Found {$orderProducts->count()} pre-order order products to process.");

        $preOrderService = app(PreOrderService::class);
        $preOrderPaymentService = app(PreOrderPaymentService::class);
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($orderProducts as $orderProduct) {
            try {
                $product = $orderProduct->product;
                if (!$product || !$product->is_preorder_enabled) {
                    $skipped++;
                    continue;
                }

                // Get active pre-order campaign
                $activePreOrder = $preOrderService->getActivePreOrderForProduct($product);
                if (!$activePreOrder) {
                    $this->warn("No active pre-order found for product ID: {$product->id}");
                    $skipped++;
                    continue;
                }

                // Check if PreOrderPayment already exists
                $order = $orderProduct->order;
                $customer = $order->user_id ? $order->user : null;
                $customerEmail = optional($order->user)->email ?: optional($order->address)->email;
                $customerName = optional($order->user)->name ?: optional($order->address)->name;

                $existing = \Botble\Ecommerce\Models\PreOrderPayment::query()
                    ->where('pre_order_id', $activePreOrder->id)
                    ->where('product_id', $product->id)
                    ->when($customer?->id, fn($q) => $q->where('customer_id', $customer->id))
                    ->when(!$customer?->id && $customerEmail, fn($q) => $q->where('customer_email', $customerEmail))
                    ->first();

                if ($existing) {
                    $this->info("PreOrderPayment already exists for Order Product ID: {$orderProduct->id}");
                    $skipped++;
                    continue;
                }

                // Extract payment type from options
                $options = (array) ($orderProduct->options ?? []);
                $paymentTypeValue = $options['preorder_payment_type']
                    ?? ($options['extras']['preorder']['payment_type'] ?? 'deposit');

                // Convert string to enum
                $paymentType = $paymentTypeValue === 'deposit' || $paymentTypeValue === PreOrderPaymentTypeEnum::DEPOSIT->value
                    ? PreOrderPaymentTypeEnum::DEPOSIT()
                    : PreOrderPaymentTypeEnum::FULL_PAYMENT();

                // Get prices
                $orderProductPrice = isset($orderProduct->price) ? (float) $orderProduct->price : null;
                $originalPrice = $options['original_price'] ?? null;
                $pivotPrice = $activePreOrder->products()->where('product_id', $product->id)->first()?->pivot?->price;
                
                $fullProductPrice = $originalPrice
                    ?? ($pivotPrice && $pivotPrice > 0 ? $pivotPrice : ($product->front_sale_price ?? $product->price));

                // Create PreOrderPayment
                $payment = $preOrderPaymentService->createPreOrderPayment(
                    $activePreOrder,
                    $product,
                    (int) ($orderProduct->qty ?? 1),
                    $paymentType,
                    $customer,
                    $customerEmail,
                    $customerName,
                    $fullProductPrice,
                    $orderProductPrice
                );

                // If order is paid, update payment status
                if ($order->payment_id || $order->is_finished) {
                    $paymentMethod = 'bank_transfer'; // Default, could be extracted from order if available
                    $preOrderPaymentService->processPayment($payment, $paymentMethod);
                }

                $this->info("Created PreOrderPayment ID: {$payment->id} for Order Product ID: {$orderProduct->id}");
                $created++;
            } catch (\Throwable $e) {
                $this->error("Error processing Order Product ID: {$orderProduct->id} - {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("\nSummary:");
        $this->info("Created: {$created}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        return 0;
    }
}
