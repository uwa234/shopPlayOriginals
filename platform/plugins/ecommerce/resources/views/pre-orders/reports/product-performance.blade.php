@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Product Performance') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Product Performance') }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Orders') }}</th>
                                    <th>{{ __('Total Quantity') }}</th>
                                    <th>{{ __('Revenue') }}</th>
                                    <th>{{ __('Average Order Value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productStats as $stat)
                                    <tr>
                                        <td>{{ $stat['product']->name }}</td>
                                        <td>{{ $stat['orders_count'] }}</td>
                                        <td>{{ $stat['total_quantity'] }}</td>
                                        <td><strong>{{ format_price($stat['revenue']) }}</strong></td>
                                        <td>{{ format_price($stat['avg_order_value']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">{{ __('No data available') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
