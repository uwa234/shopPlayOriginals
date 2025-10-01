@php
    $style = $shortcode->style ?: 1;
@endphp

{!! Theme::partial("shortcodes.ecommerce-pre-order.style-$style", compact('shortcode', 'preOrder')) !!}
