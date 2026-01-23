@php
    $style = $shortcode->style ?: 1;
    
    // Debug: If preOrder is null, try to get the first active one
    if (!$preOrder) {
        $preOrder = \Botble\Ecommerce\Models\PreOrder::query()
            ->wherePublished()
            ->where('expected_delivery_date', '>', now())
            ->with([
                'products' => function ($query) {
                    $reviewParams = \Botble\Ecommerce\Facades\EcommerceHelper::withReviewsParams();
                    if (\Botble\Ecommerce\Facades\EcommerceHelper::isReviewEnabled()) {
                        $query->withAvg($reviewParams['withAvg'][0], $reviewParams['withAvg'][1]);
                    }
                    return $query
                        ->where('ec_products.status', \Botble\Base\Enums\BaseStatusEnum::PUBLISHED)
                        ->where('is_preorder_enabled', true)
                        ->with(\Botble\Ecommerce\Facades\EcommerceHelper::withProductEagerLoadingRelations())
                        ->withCount($reviewParams['withCount']);
                },
            ])
            ->first();
    }
@endphp

{!! Theme::partial("shortcodes.ecommerce-pre-order.style-$style", compact('shortcode', 'preOrder')) !!}
