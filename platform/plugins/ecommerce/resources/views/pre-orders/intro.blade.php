@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="row justify-content-center">
        <div class="col-xxl-6 col-xl-8 col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ trans('plugins/ecommerce::pre-orders.name') }}</h4>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="ti ti-clock-hour-4" style="font-size: 4rem; color: #6c757d;"></i>
                    </div>
                    <h5 class="mb-3">{{ trans('plugins/ecommerce::pre-orders.name') }}</h5>
                    <p class="text-muted mb-4">
                        {{ trans('plugins/ecommerce::pre-orders.intro.description', ['name' => trans('plugins/ecommerce::pre-orders.name')]) }}
                    </p>
                    <a href="{{ route('pre-orders.create') }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>
                        {{ trans('plugins/ecommerce::pre-orders.create') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
