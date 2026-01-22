<?php

namespace App\Console\Commands;

use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Services\PreOrderService;
use Illuminate\Console\Command;

class FixPreOrderPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preorder:fix-payment {order_id : The order ID to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix PreOrderPayment records for an order by recalculating total_amount and remaining_amount based on full product price';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');
        
        /** @var Order|null $order */
        $order = Order::query()->find($orderId);
        
        if (!$order) {
            $this->error("Order #{$orderId} not found.");
            return 1;
        }
        
        $this->info("Processing order #{$orderId}...");
        
        $orderProducts = OrderProduct::query()
            ->where('order_id', $orderId)
            ->get();
        
        if ($orderProducts->isEmpty()) {
            $this->error("No products found for order #{$orderId}.");
            return 1;
        }
        
        $preOrderService = app(PreOrderService::class);
        $fixedCount = 0;
        
        foreach ($orderProducts as $orderProduct) {
            $product = Product::query()->find($orderProduct->product_id);
            
            if (!$product || !$product->is_preorder_enabled) {
                continue;
            }
            
            $activePreOrder = $preOrderService->getActivePreOrderForProduct($product);
            if (!$activePreOrder) {
                $this->warn("No active pre-order found for product #{$product->id}.");
                continue;
            }
            
            // Get full product price (from pre-order pivot or product's front_sale_price)
            $pivotPrice = $activePreOrder->products()
                ->where('product_id', $product->id)
                ->first()?->pivot?->price;
            $fullProductPrice = ($pivotPrice && $pivotPrice > 0) 
                ? $pivotPrice 
                : ($product->front_sale_price ?? $product->price);
            
            // Order product price is the deposit paid
            $depositPaid = (float) $orderProduct->price;
            $quantity = (int) ($orderProduct->qty ?? 1);
            
            $totalAmount = $fullProductPrice * $quantity;
            $paidAmount = $depositPaid * $quantity;
            $remainingAmount = $totalAmount - $paidAmount;
            
            // Find the PreOrderPayment record
            $customerEmail = optional($order->user)->email ?: optional($order->address)->email;
            
            $payment = PreOrderPayment::query()
                ->where('product_id', $product->id)
                ->when($order->user_id, fn($q) => $q->where('customer_id', $order->user_id))
                ->when(!$order->user_id && $customerEmail, fn($q) => $q->where('customer_email', $customerEmail))
                ->oldest('id')
                ->first();
            
            // Extract payment type from order product options
            $options = (array) ($orderProduct->options ?? []);
            $paymentTypeValue = $options['preorder_payment_type']
                ?? ($options['extras']['preorder']['payment_type'] ?? 'deposit');
            
            // Determine payment type - default to DEPOSIT if order product price is less than full price
            $paymentType = $depositPaid < $fullProductPrice 
                ? \Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum::DEPOSIT()
                : \Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum::FULL_PAYMENT();
            
            $customer = $order->user_id ? \Botble\Ecommerce\Models\Customer::find($order->user_id) : null;
            $customerEmail = optional($order->user)->email ?: optional($order->address)->email;
            $customerName = optional($order->user)->name ?: optional($order->address)->name;
            
            if (!$payment) {
                $this->warn("No PreOrderPayment found for product #{$product->id} in order #{$orderId}. Creating new record...");
                
                // Create the PreOrderPayment record
                $payment = PreOrderPayment::create([
                    'pre_order_id' => $activePreOrder->id,
                    'product_id' => $product->id,
                    'customer_id' => $customer?->id,
                    'customer_email' => $customerEmail,
                    'customer_name' => $customerName,
                    'quantity' => $quantity,
                    'product_price' => $fullProductPrice,
                    'total_amount' => $totalAmount,
                    'payment_type' => $paymentType,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'payment_status' => 'pending',
                    'payment_due_date' => $activePreOrder->pre_order_end_date,
                ]);
                
                $this->info("Created PreOrderPayment #{$payment->id} for product: {$product->name}");
            } else {
                $this->info("Found PreOrderPayment #{$payment->id} for product: {$product->name}");
                $this->line("  Current values:");
                $this->line("    total_amount: " . format_price($payment->total_amount));
                $this->line("    paid_amount: " . format_price($payment->paid_amount));
                $this->line("    remaining_amount: " . format_price($payment->remaining_amount));
                
                // Update the payment record
                $payment->update([
                    'product_price' => $fullProductPrice,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                ]);
            }
            
            $this->line("  Final values:");
            $this->line("    total_amount: " . format_price($totalAmount));
            $this->line("    paid_amount: " . format_price($paidAmount));
            $this->line("    remaining_amount: " . format_price($remainingAmount));
            $this->newLine();
            
            $fixedCount++;
        }
        
        if ($fixedCount > 0) {
            $this->info("✓ Fixed {$fixedCount} PreOrderPayment record(s) for order #{$orderId}.");
        } else {
            $this->warn("No PreOrderPayment records were fixed.");
        }
        
        return 0;
    }
}
