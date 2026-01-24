<?php

namespace Botble\Ecommerce\Services;

use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
use Botble\Ecommerce\Events\PreOrderCreated;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\PreOrderPaymentStatusHistory;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Notifications\PreOrderCancelledNotification;
use Botble\Ecommerce\Notifications\PreOrderStatusUpdateNotification;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Enums\PaymentStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class PreOrderPaymentService
{
    public function createPreOrderPayment(
        PreOrder $preOrder,
        Product $product,
        int $quantity,
        PreOrderPaymentTypeEnum $paymentType,
        ?Customer $customer = null,
        ?string $customerEmail = null,
        ?string $customerName = null,
        ?float $fullProductPrice = null,
        ?float $paidDepositAmount = null
    ): PreOrderPayment {
        // Use full product price for total_amount calculation
        // For deposits: fullProductPrice = full product price (20,000), paidDepositAmount = deposit paid (13,500)
        // For full payment: fullProductPrice = full price, paidDepositAmount = null (will use fullProductPrice)
        if ($fullProductPrice !== null && $fullProductPrice > 0) {
            $productPrice = $fullProductPrice;
        } else {
            // Use pre-order pivot price if set, otherwise use the product's front sale price (sale price if on sale, otherwise base price)
            $pivotPrice = $preOrder->products()
                ->where('product_id', $product->id)
                ->first()?->pivot?->price;
            
            if ($pivotPrice && $pivotPrice > 0) {
                $productPrice = $pivotPrice;
            } else {
                // Use front_sale_price which includes sale price if product is on sale
                $productPrice = $product->front_sale_price ?? $product->price;
            }
        }

        $totalAmount = $productPrice * $quantity;
        
        if ($paymentType === PreOrderPaymentTypeEnum::DEPOSIT) {
            // For deposits: use the actual deposit amount paid (from order product price)
            if ($paidDepositAmount !== null && $paidDepositAmount > 0) {
                // Use the actual deposit amount that was paid
                $paidAmount = $paidDepositAmount * $quantity;
            } else {
                // Fallback: calculate deposit using pre-order settings
                $paidAmount = $preOrder->calculateDepositAmount($product, $quantity);
            }
            
            $remainingAmount = $totalAmount - $paidAmount;
        } else {
            // For full payment, the full price is paid
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
            'remaining_amount' => $remainingAmount, // Total minus deposit
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
            // For deposits, the paid amount should be the deposit (total - remaining)
            $calculatedPaid = $payment->payment_type === PreOrderPaymentTypeEnum::DEPOSIT
                ? ($payment->total_amount - $payment->remaining_amount)
                : $payment->total_amount;

            $newRemaining = max($payment->total_amount - $calculatedPaid, 0);

            $payment->update([
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_data' => $paymentData,
                'payment_status' => 'paid',
                'paid_amount' => $calculatedPaid,
                'remaining_amount' => $newRemaining,
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

    public function updateStatus(
        PreOrderPayment $payment,
        string $status,
        ?string $description = null,
        $user = null
    ): PreOrderPaymentStatusHistory {
        $oldStatus = $payment->payment_status;
        
        // Update payment status
        $payment->update([
            'payment_status' => $status,
        ]);

        // Get user info
        $userId = null;
        $userType = null;
        
        if ($user) {
            $userId = $user->id ?? null;
            $userType = get_class($user);
        } elseif (Auth::check()) {
            $authUser = Auth::user();
            $userId = $authUser->id ?? null;
            $userType = get_class($authUser);
        }

        // Create status history record
        $history = PreOrderPaymentStatusHistory::create([
            'pre_order_payment_id' => $payment->id,
            'status' => $status,
            'description' => $description ?? $this->generateStatusDescription($oldStatus, $status),
            'user_id' => $userId,
            'user_type' => $userType,
        ]);

        // Send notification to customer
        if ($payment->customer) {
            Notification::send($payment->customer, new PreOrderStatusUpdateNotification($payment, $history));
        } elseif ($payment->customer_email) {
            // For guest customers, create a temporary notifiable
            $notifiable = new \stdClass();
            $notifiable->email = $payment->customer_email;
            $notifiable->name = $payment->customer_name;
            Notification::route('mail', $payment->customer_email)
                ->notify(new PreOrderStatusUpdateNotification($payment, $history));
        }

        return $history;
    }

    protected function generateStatusDescription(string $oldStatus, string $newStatus): string
    {
        $statusLabels = [
            'pending' => __('Pending'),
            'paid' => __('Paid'),
            'partially_paid' => __('Partially Paid'),
            'failed' => __('Failed'),
            'refunded' => __('Refunded'),
            'partially_refunded' => __('Partially Refunded'),
            'cancelled' => __('Cancelled'),
            'processing' => __('Processing'),
            'shipped' => __('Shipped'),
            'delivered' => __('Delivered'),
            'overdue' => __('Overdue'),
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? ucfirst($oldStatus);
        $newLabel = $statusLabels[$newStatus] ?? ucfirst($newStatus);

        return __('Status changed from :old to :new', [
            'old' => $oldLabel,
            'new' => $newLabel,
        ]);
    }

    public function processRefund(
        PreOrderPayment $payment,
        float $amount,
        ?string $reason = null
    ): bool {
        if ($amount <= 0 || $amount > $payment->paid_amount) {
            throw new \InvalidArgumentException('Invalid refund amount.');
        }

        try {
            $refundedAmount = ($payment->refunded_amount ?? 0) + $amount;
            $newPaidAmount = $payment->paid_amount - $amount;
            $newRemainingAmount = $payment->remaining_amount + $amount; // Increase remaining if partial refund

            $newStatus = $refundedAmount >= $payment->paid_amount ? 'refunded' : 'partially_refunded';

            // Update payment
            $payment->update([
                'refunded_amount' => $refundedAmount,
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => $newRemainingAmount,
                'payment_status' => $newStatus,
            ]);

            // Record refund in payment_data
            $paymentData = $payment->payment_data ?? [];
            $refunds = $paymentData['refunds'] ?? [];
            $refunds[] = [
                'amount' => $amount,
                'reason' => $reason,
                'processed_at' => now()->toDateTimeString(),
            ];
            $paymentData['refunds'] = $refunds;
            $payment->update(['payment_data' => $paymentData]);

            // Update status history
            $this->updateStatus(
                $payment,
                $newStatus,
                __('Refund processed: :amount. Reason: :reason', [
                    'amount' => format_price($amount),
                    'reason' => $reason ?? __('No reason provided'),
                ])
            );

            // Decrease pre-ordered count
            $preOrder = $payment->preOrder;
            $preOrder->products()->updateExistingPivot($payment->product_id, [
                'pre_ordered' => max(0, $preOrder->products()
                    ->where('product_id', $payment->product_id)
                    ->first()->pivot->pre_ordered - $payment->quantity),
            ]);

            // Process refund through payment gateway if supported
            if ($payment->payment_reference && is_plugin_active('payment')) {
                // Attempt to process refund through payment gateway
                // This would need to be implemented based on the payment gateway being used
                // For now, we'll just log it
                \Log::info('Refund request for payment reference: ' . $payment->payment_reference);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to process refund: ' . $e->getMessage());
            return false;
        }
    }

    public function cancelPreOrder(
        PreOrderPayment $payment,
        ?string $reason = null,
        ?string $reasonDescription = null,
        $user = null
    ): bool {
        try {
            // Update payment with cancellation info
            $payment->update([
                'cancellation_reason' => $reason,
                'cancellation_reason_description' => $reasonDescription,
                'payment_status' => 'cancelled',
            ]);

            // Update status history
            $this->updateStatus(
                $payment,
                'cancelled',
                __('Pre-order cancelled. Reason: :reason', [
                    'reason' => $reasonDescription ?? $reason ?? __('No reason provided'),
                ]),
                $user
            );

            // Process refund if payment was made
            if ($payment->paid_amount > 0) {
                $this->processRefund($payment, $payment->paid_amount, $reasonDescription ?? $reason);
            }

            // Decrease pre-ordered count
            $preOrder = $payment->preOrder;
            $preOrder->products()->updateExistingPivot($payment->product_id, [
                'pre_ordered' => max(0, $preOrder->products()
                    ->where('product_id', $payment->product_id)
                    ->first()->pivot->pre_ordered - $payment->quantity),
            ]);

            // Send cancellation notification
            if ($payment->customer) {
                Notification::send($payment->customer, new PreOrderCancelledNotification($payment, $reason, $reasonDescription));
            } elseif ($payment->customer_email) {
                Notification::route('mail', $payment->customer_email)
                    ->notify(new PreOrderCancelledNotification($payment, $reason, $reasonDescription));
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to cancel pre-order: ' . $e->getMessage());
            return false;
        }
    }
} 