<?php

namespace Botble\Ecommerce\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Rules\OnOffRule;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class PreOrderRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'pre_order_start_date' => 'required|date',
            'pre_order_end_date' => 'required|date|after:pre_order_start_date',
            'expected_delivery_date' => 'nullable|date|after:pre_order_end_date',
            'requires_deposit' => [new OnOffRule()],
            'deposit_amount' => 'nullable|numeric|min:0',
            'deposit_percentage' => 'nullable|numeric|min:0|max:100',
            'allow_full_payment' => [new OnOffRule()],
            'status' => ['required', Rule::in(BaseStatusEnum::values())],
            'selected_products' => 'nullable|array',
            'selected_products.*' => 'exists:ec_products,id',
            'products' => 'nullable|array',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.max_quantity' => 'nullable|integer|min:1',
            'products.*.deposit_amount' => 'nullable|numeric|min:0',
            'products.*.deposit_percentage' => 'nullable|numeric|min:0|max:100',
            'products.*.is_active' => 'nullable',
            'products.*.pre_ordered' => 'nullable|integer|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('plugins/ecommerce::pre-orders.name'),
            'description' => trans('plugins/ecommerce::pre-orders.description'),
            'pre_order_start_date' => trans('plugins/ecommerce::pre-orders.start_date'),
            'pre_order_end_date' => trans('plugins/ecommerce::pre-orders.end_date'),
            'expected_delivery_date' => trans('plugins/ecommerce::pre-orders.delivery_date'),
            'requires_deposit' => trans('plugins/ecommerce::pre-orders.requires_deposit'),
            'deposit_amount' => trans('plugins/ecommerce::pre-orders.deposit_amount'),
            'deposit_percentage' => trans('plugins/ecommerce::pre-orders.deposit_percentage'),
            'allow_full_payment' => trans('plugins/ecommerce::pre-orders.allow_full_payment'),
            'status' => trans('core/base::tables.status'),
            'products' => trans('plugins/ecommerce::pre-orders.products'),
        ];
    }
}
