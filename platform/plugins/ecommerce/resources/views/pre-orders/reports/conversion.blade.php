@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Conversion Rate Report') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Conversion Rate Report') }}</span>
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
                                    <th>{{ __('Views') }}</th>
                                    <th>{{ __('Pre-Orders') }}</th>
                                    <th>{{ __('Conversion Rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($conversionData as $data)
                                    <tr>
                                        <td>{{ $data['product']->name }}</td>
                                        <td>{{ $data['views'] }}</td>
                                        <td>{{ $data['pre_orders'] }}</td>
                                        <td><strong>{{ $data['conversion_rate'] }}%</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">{{ __('No data available') }}</td>
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
