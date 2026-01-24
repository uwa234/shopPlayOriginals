@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ __('Revenue Forecast') }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ __('Revenue Forecast') }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Pending Revenue') }}</h6>
                                        <h3>{{ format_price($pendingRevenue) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Average Daily Revenue') }}</h6>
                                        <h3>{{ format_price($avgDailyRevenue) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>{{ __('Total Projected Revenue') }}</h6>
                                        <h3 class="text-primary">{{ format_price($totalProjectedRevenue) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <h5>{{ __('30-Day Forecast') }}</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Projected Revenue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($forecastData as $forecast)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($forecast['date'])->format('M d, Y') }}</td>
                                        <td>{{ format_price($forecast['projected_revenue']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
