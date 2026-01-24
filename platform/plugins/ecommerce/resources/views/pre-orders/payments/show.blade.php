@php
    use Botble\Base\Facades\BaseHelper;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('title')
    {{ trans('plugins/ecommerce::pre-orders.payments.show', [
        'pre_order' => $preOrder->name,
        'payment' => $payment->id
    ]) }}
@stop

@section('content')
    <div class="widget meta-boxes">
        <div class="widget-title">
            <h4>
                <span>{{ trans('plugins/ecommerce::pre-orders.payments.show', [
                    'pre_order' => $preOrder->name,
                    'payment' => $payment->id
                ]) }}</span>
            </h4>
        </div>
        <div class="widget-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">{{ trans('plugins/ecommerce::pre-orders.payments.customer_name') }}:</th>
                            <td>{{ $payment->customer_name }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.customer_email') }}:</th>
                            <td>{{ $payment->customer_email }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.product') }}:</th>
                            <td>{{ $payment->product->name }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.quantity') }}:</th>
                            <td>{{ $payment->quantity }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.payment_type') }}:</th>
                            <td>{{ $payment->payment_type->label() }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.payment_status') }}:</th>
                            <td>
                                @if($payment->payment_status === 'paid')
                                    <span class="badge bg-success">{{ ucfirst($payment->payment_status) }}</span>
                                @elseif($payment->payment_status === 'pending')
                                    <span class="badge bg-warning">{{ ucfirst($payment->payment_status) }}</span>
                                @else
                                    <span class="badge bg-danger">{{ ucfirst($payment->payment_status) }}</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">{{ trans('plugins/ecommerce::pre-orders.payments.total_amount') }}:</th>
                            <td><strong>{{ format_price($payment->total_amount) }}</strong></td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.paid_amount') }}:</th>
                            <td><strong>{{ format_price($payment->paid_amount) }}</strong></td>
                        </tr>
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.remaining_amount') }}:</th>
                            <td>
                                @if($payment->remaining_amount > 0)
                                    <strong class="text-danger">{{ format_price($payment->remaining_amount) }}</strong>
                                @else
                                    <strong class="text-success">{{ format_price(0) }}</strong>
                                @endif
                            </td>
                        </tr>
                        @if($payment->payment_method)
                        <tr>
                            <th>{{ trans('plugins/ecommerce::pre-orders.payments.payment_method') }}:</th>
                            <td>{{ $payment->payment_method }}</td>
                        </tr>
                        @endif
                        @if($payment->payment_reference)
                        <tr>
                            <th>{{ trans('Payment Reference') }}:</th>
                            <td>{{ $payment->payment_reference }}</td>
                        </tr>
                        @endif
                        @if($payment->payment_due_date)
                        <tr>
                            <th>{{ trans('Payment Due Date') }}:</th>
                            <td>{{ $payment->payment_due_date->format('M d, Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th>{{ trans('Created At') }}:</th>
                            <td>{{ $payment->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($payment->statusHistory && $payment->statusHistory->isNotEmpty())
            <div class="row mt-4">
                <div class="col-md-12">
                    <h5>{{ trans('Status Timeline') }}</h5>
                    <div class="timeline">
                        @foreach($payment->statusHistory as $history)
                            <div class="timeline-item mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="d-flex">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="timeline-marker bg-{{ $history->status === 'paid' ? 'success' : ($history->status === 'failed' ? 'danger' : 'info') }} rounded-circle" 
                                             style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                            @if($history->status === 'paid')
                                                <i class="ti ti-check"></i>
                                            @elseif($history->status === 'failed')
                                                <i class="ti ti-x"></i>
                                            @else
                                                <i class="ti ti-clock"></i>
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
                @if($payment->payment_status === 'pending' && $payment->remaining_amount > 0)
                    <form action="{{ route('pre-orders.payments.confirm', ['preOrder' => $preOrder->id, 'payment' => $payment->id]) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            {{ trans('plugins/ecommerce::pre-orders.payments.confirm') }}
                        </button>
                    </form>
                @endif
                
                @if($payment->payment_status !== 'cancelled' && $payment->payment_status !== 'refunded')
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelPaymentModal">
                        {{ trans('plugins/ecommerce::pre-orders.payments.cancel') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Cancel Payment Modal -->
    <div class="modal fade" id="cancelPaymentModal" tabindex="-1" aria-labelledby="cancelPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('pre-orders.payments.cancel', ['preOrder' => $preOrder->id, 'payment' => $payment->id]) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelPaymentModalLabel">{{ trans('Cancel Payment') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="refund_amount" class="form-label">{{ trans('Refund Amount') }}</label>
                            <input type="number" class="form-control" id="refund_amount" name="refund_amount" 
                                   value="{{ $payment->paid_amount }}" min="0" max="{{ $payment->paid_amount }}" step="0.01">
                            <small class="text-muted">{{ trans('Maximum refundable: :amount', ['amount' => format_price($payment->paid_amount)]) }}</small>
                        </div>
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">{{ trans('Cancellation Reason') }}</label>
                            <input type="text" class="form-control" id="cancellation_reason" name="cancellation_reason">
                        </div>
                        <div class="mb-3">
                            <label for="cancellation_reason_description" class="form-label">{{ trans('Description') }}</label>
                            <textarea class="form-control" id="cancellation_reason_description" name="cancellation_reason_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('Close') }}</button>
                        <button type="submit" class="btn btn-danger">{{ trans('Cancel Payment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
