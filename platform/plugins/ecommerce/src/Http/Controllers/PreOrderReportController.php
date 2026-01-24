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

    public function conversionRate(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.conversion_rate'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        // Get product views and pre-orders for conversion calculation
        // Note: This assumes there's a product_views table or similar tracking
        $preOrders = PreOrderPayment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('product_id, COUNT(DISTINCT customer_id) as pre_order_count')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // For now, we'll use a simplified calculation
        // In a real scenario, you'd track product views separately
        $conversionData = [];
        foreach ($preOrders as $preOrder) {
            $product = \Botble\Ecommerce\Models\Product::find($preOrder->product_id);
            if ($product) {
                // Simplified: assume views based on some metric
                // In production, you'd query actual view counts
                $estimatedViews = $product->views ?? 100; // Placeholder
                $conversionRate = $estimatedViews > 0 
                    ? ($preOrder->pre_order_count / $estimatedViews) * 100 
                    : 0;

                $conversionData[] = [
                    'product' => $product,
                    'views' => $estimatedViews,
                    'pre_orders' => $preOrder->pre_order_count,
                    'conversion_rate' => round($conversionRate, 2),
                ];
            }
        }

        usort($conversionData, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return view('plugins/ecommerce::pre-orders.reports.conversion', compact(
            'conversionData',
            'startDate',
            'endDate'
        ));
    }

    public function paymentTypeDistribution(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.payment_type_distribution'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        $distribution = PreOrderPayment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                payment_type,
                COUNT(*) as count,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN payment_status = "paid" THEN total_amount ELSE 0 END) as total_value
            ')
            ->groupBy('payment_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->payment_type,
                    'count' => $item->count,
                    'revenue' => $item->revenue,
                    'total_value' => $item->total_value,
                    'percentage' => 0, // Will be calculated in view
                ];
            });

        $totalCount = $distribution->sum('count');
        $distribution = $distribution->map(function ($item) use ($totalCount) {
            $item['percentage'] = $totalCount > 0 ? round(($item['count'] / $totalCount) * 100, 2) : 0;
            return $item;
        });

        return view('plugins/ecommerce::pre-orders.reports.payment-distribution', compact(
            'distribution',
            'startDate',
            'endDate'
        ));
    }

    public function customerLifetimeValue(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.customer_lifetime_value'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(90)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        $customerStats = PreOrderPayment::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('customer_id')
            ->selectRaw('
                customer_id,
                COUNT(*) as order_count,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as total_spent,
                SUM(quantity) as total_quantity,
                MIN(created_at) as first_order_date,
                MAX(created_at) as last_order_date
            ')
            ->groupBy('customer_id')
            ->orderBy('total_spent', 'desc')
            ->take(50)
            ->get()
            ->map(function ($stat) {
                $customer = \Botble\Ecommerce\Models\Customer::find($stat->customer_id);
                return [
                    'customer' => $customer,
                    'order_count' => $stat->order_count,
                    'total_spent' => $stat->total_spent,
                    'total_quantity' => $stat->total_quantity,
                    'average_order_value' => $stat->order_count > 0 
                        ? round($stat->total_spent / $stat->order_count, 2) 
                        : 0,
                    'first_order_date' => $stat->first_order_date,
                    'last_order_date' => $stat->last_order_date,
                ];
            });

        return view('plugins/ecommerce::pre-orders.reports.customer-lifetime-value', compact(
            'customerStats',
            'startDate',
            'endDate'
        ));
    }

    public function productPerformance(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.product_performance'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        $productStats = PreOrderPayment::whereBetween('created_at', [$startDate, $endDate])
            ->with('product')
            ->selectRaw('
                product_id,
                COUNT(*) as orders_count,
                SUM(quantity) as total_quantity,
                SUM(CASE WHEN payment_status = "paid" THEN paid_amount ELSE 0 END) as revenue,
                AVG(CASE WHEN payment_status = "paid" THEN paid_amount ELSE NULL END) as avg_order_value
            ')
            ->groupBy('product_id')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($stat) {
                return [
                    'product' => $stat->product,
                    'orders_count' => $stat->orders_count,
                    'total_quantity' => $stat->total_quantity,
                    'revenue' => $stat->revenue,
                    'avg_order_value' => round($stat->avg_order_value ?? 0, 2),
                ];
            });

        return view('plugins/ecommerce::pre-orders.reports.product-performance', compact(
            'productStats',
            'startDate',
            'endDate'
        ));
    }

    public function revenueForecast(Request $request)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.reports.revenue_forecast'));

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        // Historical revenue data
        $historicalRevenue = PreOrderPayment::where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(paid_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Pending revenue (projected)
        $pendingRevenue = PreOrderPayment::where('payment_status', 'paid')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        // Calculate average daily revenue
        $days = $historicalRevenue->count();
        $avgDailyRevenue = $days > 0 
            ? $historicalRevenue->sum('revenue') / $days 
            : 0;

        // Forecast for next 30 days
        $forecastDays = 30;
        $forecastData = [];
        for ($i = 1; $i <= $forecastDays; $i++) {
            $forecastData[] = [
                'date' => Carbon::now()->addDays($i)->format('Y-m-d'),
                'projected_revenue' => $avgDailyRevenue,
            ];
        }

        // Total projected revenue
        $totalProjectedRevenue = $pendingRevenue + ($avgDailyRevenue * $forecastDays);

        return view('plugins/ecommerce::pre-orders.reports.revenue-forecast', compact(
            'historicalRevenue',
            'pendingRevenue',
            'forecastData',
            'totalProjectedRevenue',
            'avgDailyRevenue',
            'startDate',
            'endDate'
        ));
    }
} 