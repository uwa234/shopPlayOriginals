@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Customer Lifetime Value') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Customer Lifetime Value') }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Order Count') }}</th>
                                    <th>{{ __('Total Spent') }}</th>
                                    <th>{{ __('Average Order Value') }}</th>
                                    <th>{{ __('First Order') }}</th>
                                    <th>{{ __('Last Order') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customerStats as $stat)
                                    <tr>
                                        <td>{{ $stat['customer']->name ?? $stat['customer']->email }}</td>
                                        <td>{{ $stat['order_count'] }}</td>
                                        <td><strong>{{ format_price($stat['total_spent']) }}</strong></td>
                                        <td>{{ format_price($stat['average_order_value']) }}</td>
                                        <td>{{ $stat['first_order_date']->format('M d, Y') }}</td>
                                        <td>{{ $stat['last_order_date']->format('M d, Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">{{ __('No data available') }}</td>
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
