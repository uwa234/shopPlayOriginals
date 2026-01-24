<?php

namespace Botble\Ecommerce\Http\Controllers\Customers;

use Botble\Ecommerce\Forms\Fronts\CancelPreOrderForm;
use Botble\Ecommerce\Http\Controllers\BaseController;
use Botble\Ecommerce\Http\Requests\Fronts\CancelPreOrderRequest;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Botble\SeoHelper\Facades\SeoHelper;
use Botble\Theme\Facades\Theme;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PreOrderController extends BaseController
{
    public function __construct()
    {
        $version = get_cms_version();

        Theme::asset()
            ->add('customer-style', 'vendor/core/plugins/ecommerce/css/customer.css', ['bootstrap-css'], version: $version);
        Theme::asset()
            ->add('front-ecommerce-css', 'vendor/core/plugins/ecommerce/css/front-ecommerce.css', version: $version);
    }

    public function index(Request $request)
    {
        SeoHelper::setTitle(__('My Pre-Orders'));

        $customer = auth('customer')->user();
        
        $query = PreOrderPayment::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('customer_email', $customer->email);
            })
            ->with(['product', 'preOrder'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'pending') {
                $query->where('payment_status', 'pending');
            } elseif ($status === 'paid') {
                $query->where('payment_status', 'paid')
                      ->where('remaining_amount', '>', 0);
            } elseif ($status === 'completed') {
                $query->where('payment_status', 'paid')
                      ->where('remaining_amount', '<=', 0);
            }
        }

        $payments = $query->paginate(10);
        
        // Manually load order relationships for each payment (orderProduct is a method, not a relationship)
        foreach ($payments as $payment) {
            try {
                $orderProduct = $payment->orderProduct();
                if ($orderProduct) {
                    $orderProduct->load('order');
                    // Set as relation so it can be accessed in the view
                    $payment->setRelation('orderProduct', $orderProduct);
                    if ($orderProduct->order) {
                        $payment->setRelation('order', $orderProduct->order);
                    }
                }
            } catch (\Exception $e) {
                // Skip if there's an error loading the relationship
                \Log::warning('Failed to load order relationship for payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Theme::breadcrumb()
            ->add(__('My Pre-Orders'), route('customer.pre-orders.index'));

        return Theme::scope(
            'ecommerce.customers.pre-orders.list',
            compact('payments'),
            'plugins/ecommerce::themes.customers.pre-orders.list'
        )->render();
    }

    public function show(int|string $id)
    {
        $customer = auth('customer')->user();
        
        $payment = PreOrderPayment::query()
            ->where('id', $id)
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('customer_email', $customer->email);
            })
            ->with(['product', 'preOrder', 'customer', 'statusHistory'])
            ->firstOrFail();

        // Load order relationship
        $payment->load('orderProduct.order');

        SeoHelper::setTitle(__('Pre-Order Details'));

        Theme::breadcrumb()
            ->add(__('My Pre-Orders'), route('customer.pre-orders.index'))
            ->add(__('Pre-Order Details'), route('customer.pre-orders.show', $id));

        return Theme::scope(
            'ecommerce.customers.pre-orders.view',
            compact('payment'),
            'plugins/ecommerce::themes.customers.pre-orders.view'
        )->render();
    }

    public function payBalance(int|string $id)
    {
        $customer = auth('customer')->user();
        
        $payment = PreOrderPayment::query()
            ->where('id', $id)
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('customer_email', $customer->email);
            })
            ->firstOrFail();

        if (!$payment->isDeposit || $payment->remaining_amount <= 0) {
            return redirect()
                ->route('customer.pre-orders.show', $id)
                ->with('error_message', trans('plugins/ecommerce::pre-orders.no_remaining_balance'));
        }

        // Generate secure payment link with token
        $token = hash_hmac('sha256', $payment->id . $payment->customer_email . $payment->created_at, config('app.key'));
        
        return redirect()->route('public.pre-orders.pay', [
            'payment' => $payment->id,
            'token' => $token,
        ]);
    }

    public function getCancel(int|string $id)
    {
        return $this->handleCancelPreOrder($id);
    }

    public function cancel(int|string $id, CancelPreOrderRequest $request)
    {
        return $this->handleCancelPreOrder(
            $id,
            $request->input('cancellation_reason'),
            $request->input('cancellation_reason_description')
        );
    }

    protected function handleCancelPreOrder(int|string $id, ?string $reason = null, ?string $reasonDescription = null)
    {
        $customer = auth('customer')->user();
        
        $payment = PreOrderPayment::query()
            ->where('id', $id)
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhere('customer_email', $customer->email);
            })
            ->with(['product', 'preOrder'])
            ->firstOrFail();

        // Validation: Only allow cancellation if:
        // 1. Pre-order hasn't shipped (status is not 'shipped' or 'delivered')
        // 2. Within 7 days of order (configurable, default 7 days)
        // 3. Payment status allows cancellation
        $daysSinceOrder = Carbon::parse($payment->created_at)->diffInDays(Carbon::now());
        $maxCancellationDays = 7; // Can be made configurable

        if ($daysSinceOrder > $maxCancellationDays) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(__('Pre-order can only be cancelled within :days days of placing the order.', ['days' => $maxCancellationDays]));
        }

        if (in_array($payment->payment_status, ['shipped', 'delivered'])) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(__('Cannot cancel pre-order that has already been shipped or delivered.'));
        }

        // Process cancellation
        $preOrderPaymentService = app(PreOrderPaymentService::class);
        $preOrderPaymentService->cancelPreOrder($payment, $reason, $reasonDescription, $customer);

        return $this
            ->httpResponse()
            ->setMessage(__('Pre-order cancelled successfully.'));
    }
}
