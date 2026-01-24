<?php

namespace Botble\Ecommerce\Http\Controllers\Fronts;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Supports\PaymentHelper;
use Botble\Theme\Facades\Theme;
use Illuminate\Http\Request;
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
        
        if (!$payment->isDeposit || $payment->remaining_amount <= 0) {
            return $this->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/ecommerce::pre-orders.no_remaining_balance'));
        }

        if ($payment->payment_status !== 'paid') {
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

        // Generate payment data
        $callbackUrl = route('public.pre-orders.pay.callback', ['payment' => $payment->id]);
        $paymentData = $preOrderPaymentService->generatePaymentData($payment, $callbackUrl);

        // Process payment through payment gateway
        $checkoutUrl = PaymentHelper::processPayment($paymentData, $paymentMethod);

        if ($checkoutUrl) {
            return $this->httpResponse()
                ->setData(['checkout_url' => $checkoutUrl])
                ->setMessage(trans('plugins/ecommerce::checkout.processing_payment'));
        }

        return $this->httpResponse()
            ->setError()
            ->setMessage(trans('plugins/ecommerce::checkout.payment_failed'));
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
