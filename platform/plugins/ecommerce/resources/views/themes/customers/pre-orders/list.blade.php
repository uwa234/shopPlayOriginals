@extends(EcommerceHelper::viewPath('customers.master'))

@section('title', __('My Pre-Orders'))

@section('content')
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>{{ __('My Pre-Orders') }}</h4>
            <div class="btn-group" role="group">
                <a href="{{ route('customer.pre-orders.index') }}" 
                   class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ __('All') }}
                </a>
                <a href="{{ route('customer.pre-orders.index', ['status' => 'pending']) }}" 
                   class="btn btn-sm {{ request('status') === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ __('Pending') }}
                </a>
                <a href="{{ route('customer.pre-orders.index', ['status' => 'paid']) }}" 
                   class="btn btn-sm {{ request('status') === 'paid' ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ __('Balance Due') }}
                </a>
                <a href="{{ route('customer.pre-orders.index', ['status' => 'completed']) }}" 
                   class="btn btn-sm {{ request('status') === 'completed' ? 'btn-primary' : 'btn-outline-primary' }}">
                    {{ __('Completed') }}
                </a>
            </div>
        </div>
    </div>

    @if($payments->isNotEmpty())
        <div class="table-responsive customer-list-order">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>{{ __('Order number') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Pre-Order Campaign') }}</th>
                    <th>{{ __('Quantity') }}</th>
                    <th>{{ __('Total Amount') }}</th>
                    <th>{{ __('Paid') }}</th>
                    <th>{{ __('Balance') }}</th>
                    <th>{{ __('Created at') }}</th>
                    <th>{{ __('Payment method') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Expected Delivery') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($payments as $payment)
                    @php
                        $order = $payment->order();
                        $orderProduct = $payment->orderProduct();
                        $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $payment->quantity) : 0;
                        $balanceWithTax = $payment->total_amount - $payment->paid_amount + $taxAmount;
                    @endphp
                    <tr>
                        <td>
                            @if($order)
                                <strong>{{ $order->code }}</strong>
                            @else
                                <span class="text-muted">#{{ $payment->id }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $payment->product->name }}</strong>
                        </td>
                        <td>{{ $payment->preOrder->name }}</td>
                        <td>{{ $payment->quantity }}</td>
                        <td><strong>{{ format_price($payment->total_amount) }}</strong></td>
                        <td>{{ format_price($payment->paid_amount) }}</td>
                        <td>
                            @if($balanceWithTax > 0)
                                <span class="text-danger"><strong>{{ format_price($balanceWithTax) }}</strong></span>
                            @else
                                <span class="text-success">{{ format_price(0) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->created_at)
                                {{ $payment->created_at->translatedFormat('d M Y H:i:s') }}
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->payment_method)
                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</span>
                            @elseif($order && $order->payment && $order->payment->payment_channel)
                                <span class="badge bg-info">{{ $order->payment->payment_channel->label() }}</span>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->payment_status === 'paid' && $balanceWithTax > 0)
                                <span class="badge bg-warning">{{ __('Partially Paid') }}</span>
                            @elseif($payment->payment_status === 'paid' && $balanceWithTax <= 0)
                                <span class="badge bg-success">{{ __('Fully Paid') }}</span>
                            @elseif($payment->payment_status === 'pending')
                                <span class="badge bg-info">{{ __('Pending') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($payment->payment_status) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->preOrder->expected_delivery_date)
                                {{ $payment->preOrder->expected_delivery_date->format('M d, Y') }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>
                            <a
                                class="btn btn-primary btn-sm"
                                href="{{ route('customer.pre-orders.show', $payment->id) }}"
                            >
                                <x-core::icon name="ti ti-eye" />
                                {{ __('View') }}
                            </a>
                            @if($balanceWithTax > 0 && $payment->payment_status === 'paid')
                                <a
                                    class="btn btn-success btn-sm mt-1"
                                    href="{{ route('customer.pre-orders.pay', $payment->id) }}"
                                >{{ __('Pay Balance') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {!! $payments->links() !!}
        </div>
    @else
        @include(EcommerceHelper::viewPath('customers.partials.empty-state'), [
            'title' => __('No pre-orders yet!'),
            'subtitle' => __('You have not placed any pre-orders yet.'),
            'actionUrl' => route('public.products'),
            'actionLabel' => __('Browse Products'),
        ])
    @endif
@stop
