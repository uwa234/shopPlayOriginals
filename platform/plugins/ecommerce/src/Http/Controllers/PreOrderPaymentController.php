<?php

namespace Botble\Ecommerce\Http\Controllers;

use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Tables\PreOrderPaymentTable;
use Illuminate\Http\Request;

class PreOrderPaymentController extends BaseController
{
    public function index(PreOrder $preOrder, PreOrderPaymentTable $table)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.payments.name', ['pre_order' => $preOrder->name]));

        $table->setModel(PreOrderPayment::class)
            ->setQuery(PreOrderPayment::query()->where('pre_order_id', $preOrder->id));

        return $table->renderTable();
    }

    public function show(PreOrder $preOrder, PreOrderPayment $payment)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.payments.show', [
            'pre_order' => $preOrder->name,
            'payment' => $payment->id
        ]));

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
        $payment->update([
            'payment_status' => 'failed',
        ]);

        return $this
            ->httpResponse()
            ->setMessage(trans('plugins/ecommerce::pre-orders.payments.cancelled_successfully'));
    }
}
