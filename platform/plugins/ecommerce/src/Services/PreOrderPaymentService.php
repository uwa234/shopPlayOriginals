<?php

namespace Botble\Ecommerce\Services;

use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
use Botble\Ecommerce\Events\PreOrderCreated;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\Product;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Enums\PaymentStatusEnum;
use Carbon\Carbon;

class PreOrderPaymentService
{
    public function createPreOrderPayment(
        PreOrder $preOrder,
        Product $product,
        int $quantity,
        PreOrderPaymentTypeEnum $paymentType,
        ?Customer $customer = null,
        ?string $customerEmail = null,
        ?string $customerName = null
    ): PreOrderPayment {
        $productPrice = $preOrder->products()
            ->where('product_id', $product->id)
            ->first()?->pivot?->price ?? $product->price;

        $totalAmount = $productPrice * $quantity;
        
        if ($paymentType === PreOrderPaymentTypeEnum::DEPOSIT) {
            $paidAmount = $preOrder->calculateDepositAmount($product, $quantity);
            $remainingAmount = $totalAmount - $paidAmount;
        } else {
            $paidAmount = $totalAmount;
            $remainingAmount = 0;
        }

        $payment = PreOrderPayment::create([
            'pre_order_id' => $preOrder->id,
            'product_id' => $product->id,
            'customer_id' => $customer?->id,
            'customer_email' => $customerEmail ?? $customer?->email,
            'customer_name' => $customerName ?? $customer?->name,
            'quantity' => $quantity,
            'product_price' => $productPrice,
            'total_amount' => $totalAmount,
            'payment_type' => $paymentType,
            'paid_amount' => 0, // Will be updated after successful payment
            'remaining_amount' => $paidAmount, // Amount that needs to be paid
            'payment_status' => 'pending',
            'payment_due_date' => $preOrder->pre_order_end_date,
        ]);

        // Update pre-ordered count
        $preOrder->products()->updateExistingPivot($product->id, [
            'pre_ordered' => $preOrder->products()
                ->where('product_id', $product->id)
                ->first()->pivot->pre_ordered + $quantity,
        ]);

        event(new PreOrderCreated($preOrder));

        return $payment;
    }

    public function processPayment(
        PreOrderPayment $payment,
        string $paymentMethod,
        ?string $paymentReference = null,
        ?array $paymentData = null
    ): bool {
        try {
            $payment->update([
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_data' => $paymentData,
                'payment_status' => 'paid',
                'paid_amount' => $payment->remaining_amount,
                'remaining_amount' => $payment->total_amount - $payment->remaining_amount,
            ]);

            return true;
        } catch (\Exception $e) {
            $payment->update([
                'payment_status' => 'failed',
                'payment_data' => array_merge($paymentData ?? [], ['error' => $e->getMessage()]),
            ]);

            return false;
        }
    }

    public function generatePaymentData(PreOrderPayment $payment, string $callbackUrl): array
    {
        return [
            'order_id' => [$payment->id], // Use payment ID as order ID
            'amount' => $payment->remaining_amount,
            'currency' => get_application_currency()->title,
            'customer_id' => $payment->customer_id,
            'customer_type' => Customer::class,
            'address' => [
                'email' => $payment->customer_email,
                'name' => $payment->customer_name,
            ],
            'products' => [
                [
                    'id' => $payment->product_id,
                    'name' => $payment->product->name,
                    'price' => $payment->product_price,
                    'qty' => $payment->quantity,
                ]
            ],
            'callback_url' => $callbackUrl,
            'return_url' => route('public.checkout.success'),
            'cancel_url' => route('public.checkout.cancel'),
        ];
    }

    public function getPaymentProgress(PreOrderPayment $payment): array
    {
        $progress = $payment->payment_progress;
        $isCompleted = $progress >= 100;

        return [
            'progress_percent' => $progress,
            'paid_amount' => $payment->paid_amount,
            'remaining_amount' => $payment->remaining_amount,
            'total_amount' => $payment->total_amount,
            'is_completed' => $isCompleted,
            'is_deposit' => $payment->isDeposit,
            'is_full_payment' => $payment->isFullPayment,
            'payment_status' => $payment->payment_status,
        ];
    }

    public function canMakeRemainingPayment(PreOrderPayment $payment): bool
    {
        return $payment->isDeposit && 
               $payment->payment_status === 'paid' && 
               $payment->remaining_amount > 0 &&
               $payment->preOrder->allowsFullPayment();
    }

    public function createRemainingPayment(PreOrderPayment $depositPayment): PreOrderPayment
    {
        if (!$this->canMakeRemainingPayment($depositPayment)) {
            throw new \Exception('Cannot create remaining payment for this deposit payment.');
        }

        return PreOrderPayment::create([
            'pre_order_id' => $depositPayment->pre_order_id,
            'product_id' => $depositPayment->product_id,
            'customer_id' => $depositPayment->customer_id,
            'customer_email' => $depositPayment->customer_email,
            'customer_name' => $depositPayment->customer_name,
            'quantity' => $depositPayment->quantity,
            'product_price' => $depositPayment->product_price,
            'total_amount' => $depositPayment->remaining_amount,
            'payment_type' => PreOrderPaymentTypeEnum::FULL_PAYMENT,
            'paid_amount' => 0,
            'remaining_amount' => $depositPayment->remaining_amount,
            'payment_status' => 'pending',
            'payment_due_date' => $depositPayment->preOrder->expected_delivery_date,
        ]);
    }

    public function getPreOrderStats(PreOrder $preOrder): array
    {
        $payments = $preOrder->payments;
        
        $totalPayments = $payments->count();
        $paidPayments = $payments->where('payment_status', 'paid')->count();
        $pendingPayments = $payments->where('payment_status', 'pending')->count();
        $failedPayments = $payments->where('payment_status', 'failed')->count();
        
        $totalRevenue = $payments->where('payment_status', 'paid')->sum('paid_amount');
        $pendingRevenue = $payments->where('payment_status', 'pending')->sum('remaining_amount');
        
        return [
            'total_payments' => $totalPayments,
            'paid_payments' => $paidPayments,
            'pending_payments' => $pendingPayments,
            'failed_payments' => $failedPayments,
            'total_revenue' => $totalRevenue,
            'pending_revenue' => $pendingRevenue,
            'completion_rate' => $totalPayments > 0 ? ($paidPayments / $totalPayments) * 100 : 0,
        ];
    }
} 