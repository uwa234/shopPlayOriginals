<?php

namespace Botble\Ecommerce\Forms;

use Botble\Base\Forms\FormAbstract;
use Botble\Ecommerce\Http\Requests\PreOrderRequest;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\Product;

class PreOrderForm extends FormAbstract
{
    public function buildForm(): void
    {
        $this
            ->setupModel(new PreOrder())
            ->setFormOption('class', 'space-y-4')
            ->setFormOption('id', 'pre-order-form')
            ->withCustomFields()
            ->add('name', 'text', [
                'label' => trans('plugins/ecommerce::pre-orders.name'),
                'label_attr' => ['class' => 'control-label required'],
                'attr' => [
                    'placeholder' => trans('plugins/ecommerce::pre-orders.name_placeholder'),
                    'data-counter' => 120,
                ],
            ])
            ->add('description', 'textarea', [
                'label' => trans('plugins/ecommerce::pre-orders.description'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'rows' => 3,
                    'placeholder' => trans('plugins/ecommerce::pre-orders.description_placeholder'),
                    'data-counter' => 500,
                ],
            ])
            ->add('pre_order_start_date', 'dateTime', [
                'label' => trans('plugins/ecommerce::pre-orders.start_date'),
                'label_attr' => ['class' => 'control-label required'],
                'attr' => [
                    'class' => 'form-control datepicker',
                ],
            ])
            ->add('pre_order_end_date', 'dateTime', [
                'label' => trans('plugins/ecommerce::pre-orders.end_date'),
                'label_attr' => ['class' => 'control-label required'],
                'attr' => [
                    'class' => 'form-control datepicker',
                ],
            ])
            ->add('expected_delivery_date', 'dateTime', [
                'label' => trans('plugins/ecommerce::pre-orders.delivery_date'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'class' => 'form-control datepicker',
                ],
            ])
            ->add('requires_deposit', 'onOff', [
                'label' => trans('plugins/ecommerce::pre-orders.requires_deposit'),
                'label_attr' => ['class' => 'control-label'],
                'default_value' => false,
            ])
            ->add('deposit_amount', 'number', [
                'label' => trans('plugins/ecommerce::pre-orders.deposit_amount'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0',
                ],
            ])
            ->add('deposit_percentage', 'number', [
                'label' => trans('plugins/ecommerce::pre-orders.deposit_percentage'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'placeholder' => '0',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                ],
            ])
            ->add('allow_full_payment', 'onOff', [
                'label' => trans('plugins/ecommerce::pre-orders.allow_full_payment'),
                'label_attr' => ['class' => 'control-label'],
                'default_value' => true,
            ])
            ->add('status', 'customSelect', [
                'label' => trans('core/base::tables.status'),
                'label_attr' => ['class' => 'control-label required'],
                'choices' => [
                    'active' => trans('core/base::tables.active'),
                    'inactive' => trans('core/base::tables.inactive'),
                ],
            ])
            ->add('products', 'repeater', [
                'label' => trans('plugins/ecommerce::pre-orders.products'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'class' => 'form-control',
                ],
                'fields' => [
                    [
                        'type' => 'select',
                        'name' => 'product_id',
                        'label' => trans('plugins/ecommerce::pre-orders.product'),
                        'choices' => Product::query()->pluck('name', 'id')->toArray(),
                        'attr' => [
                            'class' => 'form-control select2',
                        ],
                    ],
                    [
                        'type' => 'number',
                        'name' => 'price',
                        'label' => trans('plugins/ecommerce::pre-orders.price'),
                        'attr' => [
                            'placeholder' => '0.00',
                            'step' => '0.01',
                            'min' => '0',
                        ],
                    ],
                    [
                        'type' => 'number',
                        'name' => 'max_quantity',
                        'label' => trans('plugins/ecommerce::pre-orders.max_quantity'),
                        'attr' => [
                            'placeholder' => '0',
                            'min' => '0',
                        ],
                    ],
                    [
                        'type' => 'number',
                        'name' => 'deposit_amount',
                        'label' => trans('plugins/ecommerce::pre-orders.deposit_amount'),
                        'attr' => [
                            'placeholder' => '0.00',
                            'step' => '0.01',
                            'min' => '0',
                        ],
                    ],
                    [
                        'type' => 'number',
                        'name' => 'deposit_percentage',
                        'label' => trans('plugins/ecommerce::pre-orders.deposit_percentage'),
                        'attr' => [
                            'placeholder' => '0',
                            'step' => '0.01',
                            'min' => '0',
                            'max' => '100',
                        ],
                    ],
                    [
                        'type' => 'onOff',
                        'name' => 'is_active',
                        'label' => trans('core/base::tables.active'),
                        'default_value' => true,
                    ],
                ],
            ])
            ->setBreakFieldPoint('status');
    }
}
