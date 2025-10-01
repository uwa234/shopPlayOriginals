@php
    use Botble\Ecommerce\Facades\Cart;
    use Botble\Ecommerce\Services\PreOrderService;
    use Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum;
    
    // Load CSS for preorder checkout
    add_filter(THEME_FRONT_HEADER, function($html) {
        return $html . '<link rel="stylesheet" href="' . asset('vendor/core/plugins/ecommerce/css/preorder-checkout.css') . '">';
    }, 999);
    
    $cartItems = Cart::instance('cart')->content();
    $hasPreorderItems = false;
    $preOrderService = app(PreOrderService::class);
    $preOrderData = [];
    
    // Check if cart has preorder items
    foreach ($cartItems as $item) {
        if (isset($item->options['extras']['preorder']['campaign_id'])) {
            $product = \Botble\Ecommerce\Models\Product::find($item->id);
            if ($product && $product->is_preorder_enabled) {
                $activePreOrder = $preOrderService->getActivePreOrderForProduct($product);
                if ($activePreOrder) {
                    // Check if product has specific deposit settings
                    $productPivot = null;
                    if ($activePreOrder->relationLoaded('products')) {
                        $loadedProduct = $activePreOrder->products->firstWhere('id', $product->id);
                        $productPivot = $loadedProduct?->pivot;
                    }
                    if (!$productPivot) {
                        $productPivot = $activePreOrder->products()->where('product_id', $product->id)->first()?->pivot;
                    }
                    
                    // Product has specific deposit if it has deposit_amount or deposit_percentage
                    $hasProductSpecificDeposit = !empty($productPivot?->deposit_amount) || !empty($productPivot?->deposit_percentage);
                    
                    // Debug logging
                    \Log::info('Checking product deposit options', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'has_product_specific_deposit' => $hasProductSpecificDeposit,
                        'pivot_deposit_amount' => $productPivot?->deposit_amount,
                        'pivot_deposit_percentage' => $productPivot?->deposit_percentage,
                        'global_requires_deposit' => $activePreOrder->requires_deposit,
                        'global_allow_full_payment' => $activePreOrder->allow_full_payment,
                    ]);
                    
                    // Show deposit options if:
                    // 1. Product has specific deposit settings, OR
                    // 2. Global requires_deposit is true, OR
                    // 3. Global allow_full_payment is true
                    $showDepositOptions = $hasProductSpecificDeposit || $activePreOrder->requires_deposit || $activePreOrder->allow_full_payment;
                    
                    if ($showDepositOptions) {
                        $hasPreorderItems = true;
                        $depositAmount = $activePreOrder->calculateDepositAmount($product, $item->qty);
                        $fullAmount = $item->price * $item->qty;
                        
                        // Determine actual deposit requirement for this specific product
                        $productRequiresDeposit = $hasProductSpecificDeposit ? true : $activePreOrder->requires_deposit;
                        $productAllowsFullPayment = $hasProductSpecificDeposit ? true : $activePreOrder->allow_full_payment;
                        
                        $preOrderData[$item->rowId] = [
                            'product' => $product,
                            'preorder' => $activePreOrder,
                            'deposit_amount' => $depositAmount,
                            'full_amount' => $fullAmount,
                            'quantity' => $item->qty,
                            'requires_deposit' => $productRequiresDeposit,
                            'allow_full_payment' => $productAllowsFullPayment,
                            'has_product_specific_deposit' => $hasProductSpecificDeposit,
                        ];
                    }
                }
            }
        }
    }
@endphp

@if ($hasPreorderItems)
    <div class="mb-4">
        <h5 class="checkout-payment-title">{{ __('plugins/ecommerce::pre-orders.checkout.title') }}</h5>
        <div class="payment-checkout-form">
            @foreach ($preOrderData as $rowId => $data)
                @php
                    $cartItem = Cart::instance('cart')->get($rowId);
                    $currentPaymentType = $cartItem->options['preorder_payment_type'] ?? null;
                @endphp
                <div class="preorder-payment-option-wrapper bg-light p-3 mb-3">
                    <h6 class="mb-3">{{ $data['product']->name }}</h6>
                    <p class="text-muted small mb-3">{{ __('plugins/ecommerce::pre-orders.checkout.campaign', ['name' => $data['preorder']->name]) }}</p>
                    
                    @if ($data['requires_deposit'] && !$data['allow_full_payment'])
                        {{-- Deposit only --}}
                        <div class="alert alert-info">
                            <strong>{{ __('plugins/ecommerce::pre-orders.checkout.deposit_required') }}</strong><br>
                            {{ __('plugins/ecommerce::pre-orders.checkout.deposit_required_message', ['amount' => format_price($data['deposit_amount'])]) }}
                            <input type="hidden" name="preorder_payment_type[{{ $rowId }}]" value="{{ PreOrderPaymentTypeEnum::DEPOSIT }}">
                        </div>
                    @elseif (!$data['requires_deposit'] && $data['allow_full_payment'])
                        {{-- Full payment only --}}
                        <div class="alert alert-info">
                            {{ __('plugins/ecommerce::pre-orders.checkout.full_payment_only', ['amount' => format_price($data['full_amount'])]) }}
                            <input type="hidden" name="preorder_payment_type[{{ $rowId }}]" value="{{ PreOrderPaymentTypeEnum::FULL_PAYMENT }}">
                        </div>
                    @else
                        {{-- Both options available --}}
                        <div class="payment-type-selection">
                            <div class="form-check mb-2">
                                <input 
                                    class="form-check-input preorder-payment-type-radio" 
                                    type="radio" 
                                    name="preorder_payment_type[{{ $rowId }}]" 
                                    id="payment_deposit_{{ $rowId }}" 
                                    value="{{ PreOrderPaymentTypeEnum::DEPOSIT }}"
                                    data-deposit-amount="{{ $data['deposit_amount'] }}"
                                    data-full-amount="{{ $data['full_amount'] }}"
                                    data-row-id="{{ $rowId }}"
                                    @if($currentPaymentType === PreOrderPaymentTypeEnum::DEPOSIT || (!$currentPaymentType && $data['requires_deposit'])) checked @endif
                                >
                                <label class="form-check-label" for="payment_deposit_{{ $rowId }}">
                                    <strong>{{ __('plugins/ecommerce::pre-orders.checkout.deposit_option') }}</strong>
                                    <span class="text-muted">({{ format_price($data['deposit_amount']) }})</span>
                                    <br>
                                    <small class="text-muted">
                                        {{ __('plugins/ecommerce::pre-orders.checkout.pay_now_and_later', [
                                            'amount' => format_price($data['deposit_amount']),
                                            'remaining' => format_price($data['full_amount'] - $data['deposit_amount'])
                                        ]) }}
                                    </small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input 
                                    class="form-check-input preorder-payment-type-radio" 
                                    type="radio" 
                                    name="preorder_payment_type[{{ $rowId }}]" 
                                    id="payment_full_{{ $rowId }}" 
                                    value="{{ PreOrderPaymentTypeEnum::FULL_PAYMENT }}"
                                    data-deposit-amount="{{ $data['deposit_amount'] }}"
                                    data-full-amount="{{ $data['full_amount'] }}"
                                    data-row-id="{{ $rowId }}"
                                    @if($currentPaymentType === PreOrderPaymentTypeEnum::FULL_PAYMENT || (!$currentPaymentType && !$data['requires_deposit'])) checked @endif
                                >
                                <label class="form-check-label" for="payment_full_{{ $rowId }}">
                                    <strong>{{ __('plugins/ecommerce::pre-orders.checkout.full_payment_option') }}</strong>
                                    <span class="text-muted">({{ format_price($data['full_amount']) }})</span>
                                    <br>
                                    <small class="text-muted">{{ __('plugins/ecommerce::pre-orders.checkout.pay_full_now') }}</small>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    
    <script>
        (function($) {
            $(document).ready(function() {
            console.log('Preorder payment options initialized');
            console.log('Radio buttons found:', $('.preorder-payment-type-radio').length);
            
            // Handle payment type selection changes to update the total
            $('.preorder-payment-type-radio').on('change', function() {
                console.log('=== Payment type changed ===');
                var $radio = $(this);
                var paymentType = $radio.val();
                var rowId = $radio.data('row-id');
                var depositAmount = $radio.data('deposit-amount');
                var fullAmount = $radio.data('full-amount');
                
                console.log('Selected:', {
                    paymentType: paymentType,
                    rowId: rowId,
                    depositAmount: depositAmount,
                    fullAmount: fullAmount
                });
                
                var $form = $radio.closest('form');
                console.log('Form found:', $form.length > 0);
                
                if ($form.length) {
                    var updateUrl = $form.data('update-url');
                    console.log('Update URL:', updateUrl);
                    console.log('Form data:', $form.serialize());
                    
                    if (updateUrl) {
                        // Show loading state
                        var $cartWrapper = $('.cart-item-wrapper, [data-bb-toggle="checkout-cart-price-area"]');
                        if ($cartWrapper.length) {
                            $cartWrapper.css('opacity', '0.5');
                        }
                        
                        // Trigger form update via AJAX
                        $.ajax({
                            url: updateUrl,
                            method: 'POST',
                            data: $form.serialize(),
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                console.log('=== AJAX Success ===');
                                console.log('Response:', response);
                                console.log('Response.data:', response.data);
                                
                                if (response.error === false || response.error === undefined) {
                                    // The data is in response.data object
                                    var responseData = response.data || response;
                                    
                                    console.log('Looking for cart price area...');
                                    
                                    // Try multiple selectors to find the cart totals area
                                    var $cartPriceArea = $('[data-bb-toggle="checkout-cart-price-area"]');
                                    console.log('Selector 1 [data-bb-toggle="checkout-cart-price-area"]:', $cartPriceArea.length);
                                    
                                    if ($cartPriceArea.length === 0) {
                                        $cartPriceArea = $('.cart-item-wrapper');
                                        console.log('Selector 2 .cart-item-wrapper:', $cartPriceArea.length);
                                    }
                                    
                                    if ($cartPriceArea.length === 0) {
                                        $cartPriceArea = $('.checkout-cart-price-area, .cart-totals, .order-summary, #checkout-cart-price-area');
                                        console.log('Selector 3 (class/id based):', $cartPriceArea.length);
                                    }
                                    
                                    if ($cartPriceArea.length === 0) {
                                        $cartPriceArea = $('.col-lg-5.col-md-6, .checkout-col-right').find('.card, .box, [class*="total"]').first();
                                        console.log('Selector 4 (parent-child):', $cartPriceArea.length);
                                    }
                                    
                                    console.log('Final cart price area found:', $cartPriceArea.length);
                                    
                                    // Update the cart totals dynamically without page reload
                                    if (responseData.amount) {
                                        console.log('Updating cart totals HTML');
                                        console.log('Amount HTML length:', responseData.amount.length);
                                        
                                        if ($cartPriceArea.length > 0) {
                                            console.log('Updating element:', $cartPriceArea.get(0));
                                            $cartPriceArea.html(responseData.amount);
                                            console.log('Cart price area updated!');
                                        } else {
                                            console.warn('Cart price area element not found! Reloading page to show changes...');
                                            setTimeout(function() {
                                                location.reload();
                                            }, 500);
                                            return;
                                        }
                                    } else {
                                        console.warn('No amount in response');
                                    }
                                    
                                    // Update payment methods if needed
                                    if (responseData.payment_methods) {
                                        console.log('Updating payment methods HTML');
                                        var $paymentArea = $('[data-bb-toggle="checkout-payment-methods-area"]');
                                        console.log('Payment methods area found:', $paymentArea.length);
                                        if ($paymentArea.length > 0) {
                                            $paymentArea.html(responseData.payment_methods);
                                        }
                                    }
                                    
                                    // Restore opacity
                                    if ($cartWrapper.length) {
                                        $cartWrapper.css('opacity', '1');
                                    }
                                    
                                    console.log('Cart updated successfully');
                                } else {
                                    console.error('Response error:', response);
                                    alert('Error: ' + (response.message || 'Unknown error'));
                                    location.reload(); // Fallback to reload
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('=== AJAX Error ===');
                                console.error('Status:', status);
                                console.error('Error:', error);
                                console.error('XHR:', xhr);
                                alert('Error updating payment type. The page will reload.');
                                location.reload(); // Fallback to reload on error
                            }
                        });
                    } else {
                        console.warn('No update URL found on form');
                        console.log('Form attributes:', $form.prop('attributes'));
                    }
                } else {
                    console.warn('No form found. Looking for form in page...');
                    $form = $('form#checkout-form, form.checkout-form').first();
                    console.log('Found form by ID/class:', $form.length > 0);
                    if ($form.length) {
                        // Retry with found form
                        $radio.trigger('change');
                    }
                }
            });
            });
        })(jQuery);
    </script>
@endif 