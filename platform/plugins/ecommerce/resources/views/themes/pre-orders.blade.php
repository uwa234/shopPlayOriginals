@php
    Theme::layout('full-width');
    Theme::set('pageTitle', __('Pre-Orders'));
@endphp

<section class="products-listing">
    <div class="container">
        <div class="row">
            @include(EcommerceHelper::viewPath('includes.filters'))

            <div class="col-12">
                @include(EcommerceHelper::viewPath('includes.product-items'), ['perRow' => 4])

                <div class="pagination-area">
                    {!! $products->links() !!}
                </div>
            </div>
        </div>
    </div>
</section> 