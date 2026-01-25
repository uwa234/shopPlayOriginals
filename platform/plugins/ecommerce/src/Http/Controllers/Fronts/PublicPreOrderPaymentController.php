<?php

namespace Botble\Ecommerce\Http\Controllers\Fronts;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Supports\PaymentHelper;
use Botble\Theme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class PublicPreOrderPaymentController extends BaseController
{
    public function show(Request $request, PreOrderPayment $payment)
    {
        // Verify token if provided
        $token = $request->query('token');
        if ($token && !$this->verifyPaymentToken($payment, $token)) {
            return redirect()->route('public.index')
                ->with('error_message', trans('plugins/ecommerce::pre-orders.invalid_payment_link'));
        }

        // Check if payment can be made
        $preOrderPaymentService = app(PreOrderPaymentService::class);
        
        if (!$payment->isDeposit || $payment->remaining_amount <= 0) {
            return redirect()->route('public.index')
                ->with('error_message', trans('plugins/ecommerce::pre-orders.no_remaining_balance'));
        }

        if ($payment->payment_status !== 'paid') {
            return redirect()->route('public.index')
                ->with('error_message', trans('plugins/ecommerce::pre-orders.deposit_not_paid'));
        }

        Theme::asset()->usePath()->add('checkout-css', 'css/checkout.css');
        Theme::asset()->container('footer')->usePath()->add('checkout-js', 'js/checkout.js', ['jquery']);

        return Theme::scope('ecommerce.pre-orders.pay-balance', [
            'payment' => $payment,
            'product' => $payment->product,
            'preOrder' => $payment->preOrder,
        ], 'plugins/ecommerce::themes.includes.pre-orders.pay-balance')->render();
    }

    public function process(Request $request, PreOrderPayment $payment)
    {
        // Verify token if provided
        $token = $request->input('token');
        if ($token && !$this->verifyPaymentToken($payment, $token)) {
            return $this->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/ecommerce::pre-orders.invalid_payment_link'));
        }

        // Validate payment can be made
        $preOrderPaymentService = app(PreOrderPaymentService::class);
        
        // Calculate balance with tax to check if there's actually a balance to pay
        $orderProduct = $payment->orderProduct();
        $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $payment->quantity) : 0;
        $balanceWithTax = $payment->total_amount - $payment->paid_amount + $taxAmount;
        
        // Allow payment if there's a remaining balance (with or without tax)
        // Check both balance with tax and remaining_amount to be safe
        if ($balanceWithTax <= 0 && $payment->remaining_amount <= 0) {
            return $this->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/ecommerce::pre-orders.no_remaining_balance'));
        }

        // For balance payments, the initial payment must be completed
        // Allow if payment status is 'paid' or if it's a new payment (status is 'pending' but we're paying the full amount)
        if ($payment->payment_status !== 'paid' && $balanceWithTax > 0 && $payment->paid_amount <= 0) {
            return $this->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/ecommerce::pre-orders.deposit_not_paid'));
        }

        // Get payment method
        $paymentMethod = $request->input('payment_method');
        if (!$paymentMethod) {
            return $this->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/ecommerce::checkout.payment_method_required'));
        }

        // Calculate balance with tax if provided
        $balanceWithTax = $request->input('balance_with_tax');
        if ($balanceWithTax === null) {
            // Calculate balance with tax from order product
            $orderProduct = $payment->orderProduct();
            $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $payment->quantity) : 0;
            $balanceWithTax = $payment->total_amount - $payment->paid_amount + $taxAmount;
        }

        // Generate payment data with balance including tax
        $callbackUrl = route('public.pre-orders.pay.callback', ['payment' => $payment->id]);
        $generatedPaymentData = $preOrderPaymentService->generatePaymentData($payment, $callbackUrl, (float) $balanceWithTax);

        // Prepare payment data for filter (matching checkout controller structure)
        $paymentData = [
            'error' => false,
            'message' => false,
            'amount' => (float) $generatedPaymentData['amount'],
            'currency' => strtoupper($generatedPaymentData['currency']),
            'type' => $paymentMethod,
            'charge_id' => null,
            'order_id' => $generatedPaymentData['order_id'],
            'customer_id' => $generatedPaymentData['customer_id'],
            'customer_type' => $generatedPaymentData['customer_type'],
            'address' => $generatedPaymentData['address'],
            'products' => $generatedPaymentData['products'],
            'callback_url' => $generatedPaymentData['callback_url'],
            'return_url' => $generatedPaymentData['return_url'],
            'cancel_url' => $generatedPaymentData['cancel_url'],
        ];

        // Merge payment ID into request for filter processing
        $request->merge([
            'order_id' => $payment->id,
        ]);

        // Process payment through payment gateway using filter system
        $paymentData = apply_filters(FILTER_ECOMMERCE_PROCESS_PAYMENT, $paymentData, $request);

        // Check if payment gateway returned a checkout URL
        if ($checkoutUrl = Arr::get($paymentData, 'checkoutUrl')) {
            return $this->httpResponse()
                ->setError($paymentData['error'] ?? false)
                ->setNextUrl($checkoutUrl)
                ->setData(['checkout_url' => $checkoutUrl])
                ->withInput()
                ->setMessage($paymentData['message'] ?: trans('plugins/ecommerce::checkout.processing_payment'));
        }

        // Check if payment was processed successfully (for gateways that don't redirect)
        if ($paymentData['error'] || !Arr::get($paymentData, 'charge_id')) {
            return $this->httpResponse()
                ->setError()
                ->withInput()
                ->setMessage($paymentData['message'] ?: trans('plugins/ecommerce::checkout.payment_failed'));
        }

        // Payment processed successfully without redirect
        return $this->httpResponse()
            ->setMessage(trans('plugins/ecommerce::checkout.processing_payment'));
    }

    public function callback(Request $request, PreOrderPayment $payment)
    {
        $chargeId = $request->input('charge_id');
        $status = $request->input('status');

        $preOrderPaymentService = app(PreOrderPaymentService::class);

        if ($status === PaymentStatusEnum::COMPLETED) {
            // Create remaining payment record
            $remainingPayment = $preOrderPaymentService->createRemainingPayment($payment);
            
            // Process the remaining payment
            $preOrderPaymentService->processPayment(
                $remainingPayment,
                $request->input('payment_method', 'stripe'),
                $chargeId,
                $request->all()
            );

            // Update original payment's remaining amount to 0
            $payment->update([
                'remaining_amount' => 0,
            ]);

            return redirect()->route('public.checkout.success')
                ->with('success_message', trans('plugins/ecommerce::pre-orders.balance_paid_successfully'));
        }

        return redirect()->route('public.pre-orders.pay', ['payment' => $payment->id])
            ->with('error_message', trans('plugins/ecommerce::checkout.payment_failed'));
    }

    protected function verifyPaymentToken(PreOrderPayment $payment, string $token): bool
    {
        $expectedToken = hash_hmac('sha256', $payment->id . $payment->customer_email . $payment->created_at, config('app.key'));
        return hash_equals($expectedToken, $token);
    }
}
