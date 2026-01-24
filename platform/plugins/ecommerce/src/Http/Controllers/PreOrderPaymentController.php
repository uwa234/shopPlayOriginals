<?php

namespace Botble\Ecommerce\Http\Controllers;

use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Botble\Ecommerce\Tables\PreOrderPaymentTable;
use Illuminate\Http\Request;

class PreOrderPaymentController extends BaseController
{
    public function index(PreOrder $preOrder, PreOrderPaymentTable $table)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.payments.name', ['pre_order' => $preOrder->name]));

        // The table's query() method will automatically filter by pre_order_id from the route parameter
        return $table->renderTable();
    }

    public function show(PreOrder $preOrder, PreOrderPayment $payment)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.payments.show', [
            'pre_order' => $preOrder->name,
            'payment' => $payment->id
        ]));

        $payment->load(['product', 'preOrder', 'customer', 'statusHistory']);

        return view('plugins/ecommerce::pre-orders.payments.show', compact('preOrder', 'payment'));
    }

    public function confirm(PreOrder $preOrder, PreOrderPayment $payment, Request $request)
    {
        $payment->update([
            'payment_status' => 'paid',
            'paid_amount' => $payment->remaining_amount,
            'remaining_amount' => 0,
        ]);

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/ecommerce::pre-orders.payments.confirmed_successfully'));
    }

    public function cancel(PreOrder $preOrder, PreOrderPayment $payment, Request $request)
    {
        $reason = $request->input('cancellation_reason');
        $reasonDescription = $request->input('cancellation_reason_description');
        $refundAmount = $request->input('refund_amount', $payment->paid_amount);

        $preOrderPaymentService = app(PreOrderPaymentService::class);

        // Process refund if amount specified
        if ($refundAmount > 0 && $payment->paid_amount > 0) {
            $refundAmount = min($refundAmount, $payment->paid_amount);
            $preOrderPaymentService->processRefund($payment, $refundAmount, $reasonDescription ?? $reason);
        }

        // Cancel the pre-order
        $preOrderPaymentService->cancelPreOrder(
            $payment,
            $reason,
            $reasonDescription,
            auth()->user()
        );

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/ecommerce::pre-orders.payments.cancelled_successfully'));
    }
}
