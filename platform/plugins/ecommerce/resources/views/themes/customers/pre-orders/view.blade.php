@extends(EcommerceHelper::viewPath('customers.master'))

@section('title', __('Pre-Order Details'))

@section('content')
    <div class="customer-preorder-detail">
        <div class="mb-3">
            <a href="{{ route('customer.pre-orders.index') }}" class="btn btn-link">
                <x-core::icon name="ti ti-arrow-left" />
                {{ __('Back to Pre-Orders') }}
            </a>
        </div>

        @php
            $order = $payment->order();
            $orderProduct = $payment->orderProduct();
            $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $payment->quantity) : 0;
            $balanceWithTax = $payment->total_amount - $payment->paid_amount + $taxAmount;
        @endphp

        @if($order)
            @include(EcommerceHelper::viewPath('includes.order-tracking-detail'), ['order' => $order])
        @else
            <div class="card mb-3">
                <div class="card-body">
                    <div class="customer-order-detail">
                        <div class="row">
                            <div class="col-md-6">
                                <p>
                                    <span class="d-inline-block me-1">{{ __('Pre-Order ID') }}: </span>
                                    <strong>#{{ $payment->id }}</strong>
                                </p>
                                <p>
                                    <span class="d-inline-block me-1">{{ __('Time') }}: </span>
                                    <strong>{{ $payment->created_at->translatedFormat('d M Y H:i:s') }}</strong>
                                </p>
                                <p>
                                    <span class="d-inline-block me-1">{{ __('Payment status') }}: </span>
                                    <strong class="text-info">
                                        @if($payment->payment_status === 'paid' && $balanceWithTax > 0)
                                            {{ __('Partially Paid') }}
                                        @elseif($payment->payment_status === 'paid' && $balanceWithTax <= 0)
                                            {{ __('Fully Paid') }}
                                        @elseif($payment->payment_status === 'pending')
                                            {{ __('Pending') }}
                                        @else
                                            {{ ucfirst($payment->payment_status) }}
                                        @endif
                                    </strong>
                                </p>
                                @if($payment->payment_method)
                                    <p>
                                        <span class="d-inline-block me-1">{{ __('Payment method') }}: </span>
                                        <strong class="text-info">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</strong>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0">{{ __('Pre-Order Information') }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('Product') }}:</th>
                                <td><strong>{{ $payment->product->name }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ __('Pre-Order Campaign') }}:</th>
                                <td>{{ $payment->preOrder->name }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Quantity') }}:</th>
                                <td>{{ $payment->quantity }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('Payment Type') }}:</th>
                                <td>
                                    @if($payment->is_deposit)
                                        <span class="badge bg-info">{{ __('Deposit') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ __('Full Payment') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ __('Total Amount') }}:</th>
                                <td><strong class="text-primary">{{ format_price($payment->total_amount) }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ __('Amount Paid') }}:</th>
                                <td><strong class="text-success">{{ format_price($payment->paid_amount) }}</strong></td>
                            </tr>
                            @if($taxAmount > 0)
                                <tr>
                                    <th>{{ __('Tax') }}:</th>
                                    <td><strong>{{ format_price($taxAmount) }}</strong></td>
                                </tr>
                            @endif
                            <tr>
                                <th>{{ __('Balance Remaining') }}:</th>
                                <td>
                                    @if($balanceWithTax > 0)
                                        <strong class="text-danger">{{ format_price($balanceWithTax) }}</strong>
                                    @else
                                        <strong class="text-success">{{ format_price(0) }}</strong>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Payment Progress') }}:</th>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar {{ $balanceWithTax > 0 ? 'bg-warning' : 'bg-success' }}" 
                                             role="progressbar" 
                                             style="width: {{ $payment->payment_progress }}%"
                                             aria-valuenow="{{ $payment->payment_progress }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {{ number_format($payment->payment_progress, 1) }}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('Expected Delivery') }}:</th>
                                <td>
                                    @if($payment->preOrder->expected_delivery_date)
                                        <strong>{{ $payment->preOrder->expected_delivery_date->format('F d, Y') }}</strong>
                                    @else
                                        <span class="text-muted">{{ __('To be announced') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @if($payment->payment_due_date)
                            <tr>
                                <th>{{ __('Payment Due Date') }}:</th>
                                <td>
                                    <strong>{{ $payment->payment_due_date->format('F d, Y') }}</strong>
                                    @if($payment->payment_due_date->isPast() && $balanceWithTax > 0)
                                        <span class="badge bg-danger ms-2">{{ __('Overdue') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if($orderProduct && $order)
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Products') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-3">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th class="text-center">{{ __('Image') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th class="text-center">{{ __('Amount') }}</th>
                                    <th class="text-end" style="width: 100px">{{ __('Quantity') }}</th>
                                    <th class="price text-end">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center">1</td>
                                    <td class="text-center">
                                        <img src="{{ RvMedia::getImageUrl($orderProduct->product_image, 'thumb', false, RvMedia::getDefaultImage()) }}"
                                            alt="{{ $orderProduct->product_name }}" width="50">
                                    </td>
                                    <td>
                                        {!! BaseHelper::clean($orderProduct->product_name) !!}
                                        @if ($sku = Arr::get($orderProduct->options, 'sku'))
                                            ({{ $sku }})
                                        @endif
                                    </td>
                                    <td class="text-center">{{ format_price($orderProduct->price) }}</td>
                                    <td class="text-center">{{ $orderProduct->qty }}</td>
                                    <td class="money text-end">
                                        <strong>
                                            {{ format_price($orderProduct->price * $orderProduct->qty) }}
                                        </strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if (EcommerceHelper::isTaxEnabled() && $taxAmount > 0)
                        <p>
                            <span class="d-inline-block me-1">{{ __('Tax') }}:</span>
                            <strong class="order-detail-value"> {{ format_price($taxAmount) }} </strong>
                        </p>
                    @endif

                    <p>
                        <span class="d-inline-block me-1">{{ __('Total Amount') }}: </span>
                        <strong>{{ format_price($payment->total_amount) }}</strong>
                    </p>
                    <p>
                        <span class="d-inline-block me-1">{{ __('Amount Paid') }}: </span>
                        <strong>{{ format_price($payment->paid_amount) }}</strong>
                    </p>
                    <p>
                        <span class="d-inline-block me-1">{{ __('Balance Remaining') }}: </span>
                        <strong class="{{ $balanceWithTax > 0 ? 'text-danger' : 'text-success' }}">{{ format_price($balanceWithTax) }}</strong>
                    </p>
                </div>
            </div>
        @endif

        @if($payment->product)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Product Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        @if($payment->product->image)
                            <img src="{{ RvMedia::getImageUrl($payment->product->image, 'thumb') }}" 
                                 alt="{{ $payment->product->name }}" 
                                 class="img-fluid rounded">
                        @endif
                    </div>
                    <div class="col-md-9">
                        <h5>{{ $payment->product->name }}</h5>
                        @if($payment->product->description)
                            <p class="text-muted">{{ Str::limit(strip_tags($payment->product->description), 200) }}</p>
                        @endif
                        <p>
                            <strong>{{ __('Price') }}:</strong> 
                            <span class="text-primary">{{ format_price($payment->product_price) }}</span>
                        </p>
                        <a href="{{ $payment->product->url }}" 
                           class="btn btn-sm btn-outline-primary" 
                           target="_blank">
                            {{ __('View Product') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($payment->preOrder->description)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Pre-Order Campaign Details') }}</h5>
            </div>
            <div class="card-body">
                {!! BaseHelper::clean($payment->preOrder->description) !!}
            </div>
        </div>
        @endif

        <!-- Status Timeline -->
        @if($payment->statusHistory && $payment->statusHistory->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Status Timeline') }}</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    @foreach($payment->statusHistory as $history)
                        <div class="timeline-item mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="timeline-marker bg-{{ $history->status === 'paid' ? 'success' : ($history->status === 'failed' ? 'danger' : 'info') }} rounded-circle" 
                                         style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        @if($history->status === 'paid')
                                            <x-core::icon name="ti ti-check" />
                                        @elseif($history->status === 'failed')
                                            <x-core::icon name="ti ti-x" />
                                        @else
                                            <x-core::icon name="ti ti-clock" />
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="badge bg-{{ $history->status === 'paid' ? 'success' : ($history->status === 'failed' ? 'danger' : 'info') }}">
                                                    {{ ucfirst(str_replace('_', ' ', $history->status)) }}
                                                </span>
                                            </h6>
                                            @if($history->description)
                                                <p class="mb-1 text-muted">{{ $history->description }}</p>
                                            @endif
                                        </div>
                                        <small class="text-muted">{{ $history->created_at->format('M d, Y H:i') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="mt-3">
            <div class="d-flex flex-wrap gap-2">
                @if($balanceWithTax > 0 && $payment->payment_status === 'paid')
                    <form action="{{ route('public.pre-orders.pay.process', $payment->id) }}" method="POST" class="d-inline" id="pay-balance-form-{{ $payment->id }}">
                        @csrf
                        <input type="hidden" name="payment_method" value="{{ setting('default_payment_method', 'paystack') }}">
                        <input type="hidden" name="balance_with_tax" value="{{ $balanceWithTax }}">
                        <button type="submit" class="btn btn-success btn-lg pay-balance-btn" data-payment-id="{{ $payment->id }}" data-amount="{{ $balanceWithTax }}">
                            <x-core::icon name="ti ti-credit-card" />
                            {{ __('Pay Remaining Balance') }} ({{ format_price($balanceWithTax) }})
                        </button>
                    </form>
                @endif
                <a href="{{ $payment->product->url }}" 
                   class="btn btn-outline-primary">
                    <x-core::icon name="ti ti-eye" />
                    {{ __('View Product') }}
                </a>
            </div>
        </div>
    </div>
@stop

@push('footer')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Pay Balance button clicks to trigger Paystack payment
    document.querySelectorAll('.pay-balance-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const paymentId = this.dataset.paymentId;
            const amount = parseFloat(this.dataset.amount);
            
            // Show loading state
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Processing...') }}';
            
            // Submit form via AJAX to get Paystack URL
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]').value
                },
                body: new URLSearchParams(new FormData(form))
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.message || '{{ __('Payment failed. Please try again.') }}');
                    this.disabled = false;
                    this.innerHTML = originalText;
                } else if (data.data && data.data.checkout_url) {
                    // Open Paystack payment page in a popup window (modal-like)
                    const paystackWindow = window.open(
                        data.data.checkout_url,
                        'PaystackPayment',
                        'width=800,height=600,scrollbars=yes,resizable=yes'
                    );
                    
                    // Monitor the popup window for when payment is complete
                    const checkClosed = setInterval(function() {
                        if (paystackWindow.closed) {
                            clearInterval(checkClosed);
                            // Reload page to show updated payment status
                            window.location.reload();
                        }
                    }, 500);
                } else {
                    // If no checkout_url, try to submit form normally
                    form.submit();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('{{ __('An error occurred. Please try again.') }}');
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });
});
</script>
@endpush
