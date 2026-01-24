<?php

use Botble\Base\Facades\AdminHelper;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function (): void {
    Route::group(['namespace' => 'Botble\Ecommerce\Http\Controllers', 'prefix' => 'ecommerce'], function (): void {
        Route::group(['prefix' => 'pre-orders', 'as' => 'pre-orders.'], function (): void {
            Route::resource('', 'PreOrderController')
                ->parameters(['' => 'preOrder']);

            Route::delete('items/destroy', [
                'as' => 'deletes',
                'uses' => 'PreOrderController@deletes',
                'permission' => 'pre-orders.destroy',
            ]);

            Route::match(['get', 'post'], '{preOrder}/payments', [
                'as' => 'payments',
                'uses' => 'PreOrderPaymentController@index',
                'permission' => 'pre-orders.edit',
            ]);

            Route::get('{preOrder}/payments/{payment}', [
                'as' => 'payments.show',
                'uses' => 'PreOrderPaymentController@show',
                'permission' => 'pre-orders.edit',
            ]);

            Route::post('{preOrder}/payments/{payment}/confirm', [
                'as' => 'payments.confirm',
                'uses' => 'PreOrderPaymentController@confirm',
                'permission' => 'pre-orders.edit',
            ]);

            Route::post('{preOrder}/payments/{payment}/cancel', [
                'as' => 'payments.cancel',
                'uses' => 'PreOrderPaymentController@cancel',
                'permission' => 'pre-orders.edit',
            ]);

            Route::get('reports', [
                'as' => 'reports',
                'uses' => 'PreOrderReportController@index',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/export', [
                'as' => 'reports.export',
                'uses' => 'PreOrderReportController@export',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/conversion-rate', [
                'as' => 'reports.conversion-rate',
                'uses' => 'PreOrderReportController@conversionRate',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/payment-type-distribution', [
                'as' => 'reports.payment-type-distribution',
                'uses' => 'PreOrderReportController@paymentTypeDistribution',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/customer-lifetime-value', [
                'as' => 'reports.customer-lifetime-value',
                'uses' => 'PreOrderReportController@customerLifetimeValue',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/product-performance', [
                'as' => 'reports.product-performance',
                'uses' => 'PreOrderReportController@productPerformance',
                'permission' => 'pre-orders.index',
            ]);

            Route::get('reports/revenue-forecast', [
                'as' => 'reports.revenue-forecast',
                'uses' => 'PreOrderReportController@revenueForecast',
                'permission' => 'pre-orders.index',
            ]);
        });
    });
}); 