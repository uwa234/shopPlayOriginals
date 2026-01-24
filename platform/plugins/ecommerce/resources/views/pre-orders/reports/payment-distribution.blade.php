@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Payment Type Distribution') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Payment Type Distribution') }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('Payment Type') }}</th>
                                    <th>{{ __('Count') }}</th>
                                    <th>{{ __('Percentage') }}</th>
                                    <th>{{ __('Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($distribution as $item)
                                    <tr>
                                        <td>{{ $item['type']->label() }}</td>
                                        <td>{{ $item['count'] }}</td>
                                        <td><strong>{{ $item['percentage'] }}%</strong></td>
                                        <td>{{ format_price($item['revenue']) }}</td>
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
