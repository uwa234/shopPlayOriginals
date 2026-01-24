@extends(Theme::getThemeNamespace() . '::views.master')

@section('content')
    <section class="section-checkout">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('Pay Remaining Balance') }}</h3>
                        </div>
                        <div class="card-body">
                            @if (session('error_message'))
                                <div class="alert alert-danger">
                                    {{ session('error_message') }}
                                </div>
                            @endif

                            <div class="mb-4">
                                <h5>{{ __('Pre-Order Details') }}</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <td>{{ $payment->product->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Pre-Order Campaign') }}</th>
                                        <td>{{ $payment->preOrder->name }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Quantity') }}</th>
                                        <td>{{ $payment->quantity }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Total Amount') }}</th>
                                        <td><strong>{{ format_price($payment->total_amount) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Deposit Paid') }}</th>
                                        <td>{{ format_price($payment->paid_amount) }}</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <th>{{ __('Remaining Balance') }}</th>
                                        <td><strong>{{ format_price($payment->remaining_amount) }}</strong></td>
                                    </tr>
                                </table>
                            </div>

                            <form action="{{ route('public.pre-orders.pay.process', $payment->id) }}" method="POST" id="payment-form">
                                @csrf
                                @if(request()->has('token'))
                                    <input type="hidden" name="token" value="{{ request()->query('token') }}">
                                @endif

                                <div class="mb-4">
                                    <h5>{{ __('Select Payment Method') }}</h5>
                                    @if (is_plugin_active('payment'))
                                        @php
                                            $paymentMethods = \Botble\Payment\Supports\PaymentHelper::getAvailableGateways();
                                        @endphp
                                        @if ($paymentMethods)
                                            <div class="payment-methods">
                                                @foreach ($paymentMethods as $method => $gateway)
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="payment_method" 
                                                               id="payment_method_{{ $method }}" value="{{ $method }}" 
                                                               {{ $loop->first ? 'checked' : '' }} required>
                                                        <label class="form-check-label" for="payment_method_{{ $method }}">
                                                            {{ $gateway->getLabel() }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-muted">{{ __('No payment methods available.') }}</p>
                                        @endif
                                    @else
                                        <p class="text-muted">{{ __('Payment plugin is not active.') }}</p>
                                    @endif
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        {{ __('Pay Remaining Balance') }} - {{ format_price($payment->remaining_amount) }}
                                    </button>
                                    <a href="{{ route('public.index') }}" class="btn btn-link">
                                        {{ __('Cancel') }}
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
