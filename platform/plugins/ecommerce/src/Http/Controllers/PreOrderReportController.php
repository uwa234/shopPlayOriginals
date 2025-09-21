<?php

namespace Botble\Ecommerce\Http\Controllers;

use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreOrderReportController extends BaseController
{
    public function __construct(
        protected PreOrderPaymentService $preOrderPaymentService
    ) {
    }

    public function index(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.name'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        // Overview Stats
        $totalPreOrders = PreOrder::count();
        $activePreOrders = PreOrder::active()->count();
        $expiredPreOrders = PreOrder::expired()->count();
        $totalPayments = PreOrderPayment::count();
        $totalRevenue = PreOrderPayment::where('payment_status', 'paid')->sum('paid_amount');
        $pendingRevenue = PreOrderPayment::where('payment_status', 'pending')->sum('remaining_amount');

        // Date Range Stats
        $dateRangeStats = PreOrderPayment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN payment_status = "pending" THEN remaining_amount ELSE 0 END) as pending,
                COUNT(CASE WHEN payment_status = "paid" THEN 1 END) as successful_payments,
                COUNT(CASE WHEN payment_status = "failed" THEN 1 END) as failed_payments
            ')
            ->first();

        // Top Performing Pre-Orders
        $topPreOrders = PreOrder::withCount('payments')
            ->with(['payments' => function ($query) {
                $query->where('payment_status', 'paid');
            }])
            ->orderBy('payments_count', 'desc')
            ->take(10)
            ->get()
            ->map(function ($preOrder) {
                $stats = $this->preOrderPaymentService->getPreOrderStats($preOrder);
                return [
                    'pre_order' => $preOrder,
                    'stats' => $stats,
                ];
            });

        // Payment Type Distribution
        $paymentTypeStats = PreOrderPayment::selectRaw('
                payment_type,
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as revenue
            ')
            ->groupBy('payment_type')
            ->get();

        // Daily Revenue Chart Data (last 30 days)
        $dailyRevenue = PreOrderPayment::where('payment_status', 'paid')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(paid_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('revenue', 'date');

        // Product Performance
        $productStats = PreOrderPayment::with('product')
            ->selectRaw('
                product_id,
                COUNT(*) as orders_count,
                SUM(quantity) as total_quantity,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as revenue
            ')
            ->groupBy('product_id')
            ->orderBy('revenue', 'desc')
            ->take(10)
            ->get();

        return view('plugins/ecommerce::pre-orders.reports.index', compact(
            'totalPreOrders',
            'activePreOrders',
            'expiredPreOrders',
            'totalPayments',
            'totalRevenue',
            'pendingRevenue',
            'dateRangeStats',
            'topPreOrders',
            'paymentTypeStats',
            'dailyRevenue',
            'productStats',
            'startDate',
            'endDate'
        ));
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        $payments = PreOrderPayment::with(['preOrder', 'product', 'customer'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $filename = 'pre-order-report-' . $startDate . '-to-' . $endDate . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($payments) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Payment ID',
                'Pre-Order',
                'Product',
                'Customer Name',
                'Customer Email',
                'Quantity',
                'Product Price',
                'Total Amount',
                'Payment Type',
                'Paid Amount',
                'Remaining Amount',
                'Payment Status',
                'Payment Method',
                'Payment Reference',
                'Payment Date',
                'Due Date',
            ]);

            // CSV Data
            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment->id,
                    $payment->preOrder->name,
                    $payment->product->name,
                    $payment->customer_name,
                    $payment->customer_email,
                    $payment->quantity,
                    $payment->product_price,
                    $payment->total_amount,
                    $payment->payment_type->label(),
                    $payment->paid_amount,
                    $payment->remaining_amount,
                    $payment->payment_status,
                    $payment->payment_method,
                    $payment->payment_reference,
                    $payment->created_at->format('Y-m-d H:i:s'),
                    $payment->payment_due_date?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getChartData(Request $request)
    {
        $period = $request->get('period', '30'); // days
        $startDate = Carbon::now()->subDays($period);

        $data = PreOrderPayment::where('payment_status', 'paid')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(paid_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'labels' => $data->pluck('date'),
            'revenue' => $data->pluck('revenue'),
            'orders' => $data->pluck('orders'),
        ]);
    }
} 