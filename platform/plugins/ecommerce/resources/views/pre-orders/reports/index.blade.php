@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Pre-Order Reports') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Pre-Order Reports') }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <div class="mb-3">
                            <h5>{{ __('Overview') }}</h5>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Total Pre-Orders') }}</h6>
                                        <h3>{{ $totalPreOrders }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Active Pre-Orders') }}</h6>
                                        <h3>{{ $activePreOrders }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Total Revenue') }}</h6>
                                        <h3>{{ format_price($totalRevenue) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Pending Revenue') }}</h6>
                                        <h3>{{ format_price($pendingRevenue) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <a href="{{ route('pre-orders.reports.conversion-rate') }}" class="btn btn-info">
                                {{ __('Conversion Rate Report') }}
                            </a>
                            <a href="{{ route('pre-orders.reports.payment-type-distribution') }}" class="btn btn-info">
                                {{ __('Payment Type Distribution') }}
                            </a>
                            <a href="{{ route('pre-orders.reports.customer-lifetime-value') }}" class="btn btn-info">
                                {{ __('Customer Lifetime Value') }}
                            </a>
                            <a href="{{ route('pre-orders.reports.product-performance') }}" class="btn btn-info">
                                {{ __('Product Performance') }}
                            </a>
                            <a href="{{ route('pre-orders.reports.revenue-forecast') }}" class="btn btn-info">
                                {{ __('Revenue Forecast') }}
                            </a>
                            <a href="{{ route('pre-orders.reports.export') }}" class="btn btn-success">
                                {{ __('Export Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
