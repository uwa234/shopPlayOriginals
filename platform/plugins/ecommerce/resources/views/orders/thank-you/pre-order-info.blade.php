@php
    // Handle both collection and array
    $payments = $preOrderPayments instanceof \Illuminate\Support\Collection 
        ? $preOrderPayments 
        : collect($preOrderPayments);
    
    $totalDepositPaid = 0;
    $totalRemainingBalance = 0;
    $hasRemainingBalance = false;
    
    foreach ($payments as $payment) {
        // Handle both model instance and array
        $paymentModel = $payment instanceof \Botble\Ecommerce\Models\PreOrderPayment 
            ? $payment 
            : \Botble\Ecommerce\Models\PreOrderPayment::find($payment['id'] ?? $payment->id ?? null);
        
        if (!$paymentModel) {
            continue;
        }
        
        $orderProduct = $paymentModel->orderProduct();
        $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $paymentModel->quantity) : 0;
        $balanceWithTax = $paymentModel->total_amount - $paymentModel->paid_amount + $taxAmount;
        
        $totalDepositPaid += $paymentModel->paid_amount;
        $totalRemainingBalance += $balanceWithTax;
        
        if ($balanceWithTax > 0) {
            $hasRemainingBalance = true;
        }
    }
@endphp

<div class="order-preorder-info mt-4 p-3 bg-light rounded">
    <h4 class="mb-3">
        <x-core::icon name="ti ti-clock-hour-4" />
        {{ __('Pre-Order Information') }}
    </h4>
    
    @foreach ($payments as $payment)
        @php
            // Handle both model instance and array
            $paymentModel = $payment instanceof \Botble\Ecommerce\Models\PreOrderPayment 
                ? $payment 
                : \Botble\Ecommerce\Models\PreOrderPayment::find($payment['id'] ?? $payment->id ?? null);
            
            if (!$paymentModel) {
                continue;
            }
            
            $orderProduct = $paymentModel->orderProduct();
            $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $paymentModel->quantity) : 0;
            $balanceWithTax = $paymentModel->total_amount - $paymentModel->paid_amount + $taxAmount;
        @endphp
        <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <strong>{{ $paymentModel->product->name }}</strong>
                    <br>
                    <small class="text-muted">{{ __('Pre-Order Campaign') }}: {{ $paymentModel->preOrder->name }}</small>
                </div>
                <div class="text-end">
                    @if($paymentModel->is_deposit)
                        <span class="badge bg-warning">{{ __('Deposit Payment') }}</span>
                    @else
                        <span class="badge bg-success">{{ __('Full Payment') }}</span>
                    @endif
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col-6">
                    <small class="text-muted">{{ __('Total Amount') }}:</small>
                    <div><strong>{{ format_price($paymentModel->total_amount) }}</strong></div>
                </div>
                <div class="col-6">
                    <small class="text-muted">{{ __('Deposit Paid') }}:</small>
                    <div><strong class="text-success">{{ format_price($paymentModel->paid_amount) }}</strong></div>
                </div>
            </div>
            
            @if($balanceWithTax > 0)
                <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">{{ __('Balance Remaining') }}:</small>
                            <div><strong class="text-danger">{{ format_price($balanceWithTax) }}</strong></div>
                            @if($taxAmount > 0)
                                <small class="text-muted">({{ __('includes tax') }}: {{ format_price($taxAmount) }})</small>
                            @endif
                        </div>
                        <div>
                            @php
                                // Generate secure payment token for public access
                                $paymentToken = hash_hmac('sha256', $paymentModel->id . $paymentModel->customer_email . $paymentModel->created_at, config('app.key'));
                                // Calculate balance with tax
                                $orderProduct = $paymentModel->orderProduct();
                                $taxAmount = $orderProduct ? ($orderProduct->tax_amount * $paymentModel->quantity) : 0;
                                $balanceWithTax = $paymentModel->total_amount - $paymentModel->paid_amount + $taxAmount;
                            @endphp
                            <form action="{{ route('public.pre-orders.pay.process', $paymentModel->id) }}" method="POST" class="d-inline" id="pay-balance-form-{{ $paymentModel->id }}">
                                @csrf
                                <input type="hidden" name="token" value="{{ $paymentToken }}">
                                <input type="hidden" name="payment_method" value="paystack">
                                <input type="hidden" name="balance_with_tax" value="{{ $balanceWithTax }}">
                                <button type="submit" class="btn btn-sm btn-success pay-balance-btn" data-payment-id="{{ $paymentModel->id }}" data-amount="{{ $balanceWithTax }}">
                                    <x-core::icon name="ti ti-credit-card" />
                                    {{ __('Pay Balance') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-2">
                    <span class="badge bg-success">{{ __('Fully Paid') }}</span>
                </div>
            @endif
            
            @if($paymentModel->preOrder->expected_delivery_date)
                <div class="mt-2">
                    <small class="text-muted">
                        <x-core::icon name="ti ti-calendar" />
                        {{ __('Expected Delivery') }}: {{ $paymentModel->preOrder->expected_delivery_date->format('F d, Y') }}
                    </small>
                </div>
            @endif
        </div>
    @endforeach
    
    @if($hasRemainingBalance)
        <div class="mt-3 pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ __('Total Balance Remaining') }}:</strong>
                </div>
                <div>
                    <strong class="text-danger fs-5">{{ format_price($totalRemainingBalance) }}</strong>
                </div>
            </div>
            <div class="mt-2">
                <a href="{{ route('customer.pre-orders.index') }}" class="btn btn-primary">
                    <x-core::icon name="ti ti-eye" />
                    {{ __('View All Pre-Orders') }}
                </a>
            </div>
        </div>
    @endif
</div>

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
