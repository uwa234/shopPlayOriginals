<?php

namespace Botble\Ecommerce\Forms;

use Botble\Base\Forms\FormAbstract;
use Botble\Ecommerce\Http\Requests\PreOrderRequest;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\Product;
use Botble\Base\Enums\BaseStatusEnum;

class PreOrderForm extends FormAbstract
{
    public function buildForm(): void
    {
        // Get available products for selection
        $products = Product::query()
            ->where('status', BaseStatusEnum::PUBLISHED)
            ->where('is_variation', false)
            ->select(['id', 'name', 'price', 'sale_price', 'image', 'sku'])
            ->get()
            ->mapWithKeys(function ($product) {
                $price = $product->sale_price ?: $product->price;
                $label = $product->name . ' (SKU: ' . $product->sku . ') - $' . number_format($price, 2);
                return [$product->id => $label];
            });

        $selectedProducts = [];
        if ($this->getModel() && $this->getModel()->exists) {
            $selectedProducts = $this->getModel()->products->pluck('id')->toArray();
        }

        $this
            ->setupModel(new PreOrder())
            ->setFormOption('class', 'space-y-4')
            ->setFormOption('id', 'pre-order-form')
            ->withCustomFields()
            ->add('name', 'text', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.name'),
                'label_attr' => ['class' => 'control-label required'],
                'attr' => [
                    'placeholder' => trans('plugins/ecommerce::pre-orders.forms.name_placeholder'),
                    'data-counter' => 120,
                ],
            ])
            ->add('description', 'textarea', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.description'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'rows' => 3,
                    'placeholder' => trans('plugins/ecommerce::pre-orders.forms.description_placeholder'),
                    'data-counter' => 500,
                ],
            ])
            ->add('custom_pre_order_message', 'textarea', [
                'label' => 'Custom Pre-Order Message',
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Enter a custom message to display for pre-orders (optional)',
                    'data-counter' => 255,
                ],
            ])
            ->add('pre_order_start_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.start_date'),
                'label_attr' => ['class' => 'control-label required'],
                'default_value' => now(),
            ])
            ->add('pre_order_end_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.end_date'),
                'label_attr' => ['class' => 'control-label required'],
                'default_value' => now()->addDays(30),
            ])
            ->add('expected_delivery_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.delivery_date'),
                'label_attr' => ['class' => 'control-label'],
                'default_value' => now()->addDays(45),
            ])
            ->add('selected_products', 'multiCheckList', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.products'),
                'label_attr' => ['class' => 'control-label'],
                'choices' => $products,
                'value' => $selectedProducts,
                'attr' => [
                    'class' => 'form-control',
                ],
                'help_block' => [
                    'text' => 'Select one or more products to include in this pre-order campaign.',
                ],
            ])
            ->add('product_settings_section', 'html', [
                'html' => view('plugins/ecommerce::pre-orders.partials.product-settings-dynamic', [
                    'products' => Product::query()
                        ->where('status', BaseStatusEnum::PUBLISHED)
                        ->where('is_variation', false)
                        ->select(['id', 'name', 'price', 'sale_price', 'image', 'sku'])
                        ->get()
                        ->map(function ($product) {
                            return [
                                'id' => $product->id,
                                'name' => $product->name,
                                'sku' => $product->sku,
                                'price' => $product->price,
                                'sale_price' => $product->sale_price,
                                'image' => $product->image ? \Botble\Media\Facades\RvMedia::getImageUrl($product->image, 'thumb') : null,
                            ];
                        })
                ])->render(),
            ])
            ->add('requires_deposit', 'onOff', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.requires_deposit'),
                'label_attr' => ['class' => 'control-label'],
                'default_value' => false,
            ])
            ->add('deposit_amount', 'number', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.deposit_amount'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0',
                ],
            ])
            ->add('deposit_percentage', 'number', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.deposit_percentage'),
                'label_attr' => ['class' => 'control-label'],
                'attr' => [
                    'placeholder' => '0',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100',
                ],
            ])
            ->add('allow_full_payment', 'onOff', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.allow_full_payment'),
                'label_attr' => ['class' => 'control-label'],
                'default_value' => true,
            ])
            ->add('status', 'customSelect', [
                'label' => trans('core/base::tables.status'),
                'label_attr' => ['class' => 'control-label required'],
                'choices' => BaseStatusEnum::labels(),
                'default_value' => BaseStatusEnum::PUBLISHED,
            ])
            ->setBreakFieldPoint('status');

        // Add product-specific settings for existing pre-order
        if ($this->getModel() && $this->getModel()->exists && $this->getModel()->products->isNotEmpty()) {
            foreach ($this->getModel()->products as $product) {
                $pivot = $product->pivot;
                
                $this->add("product_section_{$product->id}", 'html', [
                    'html' => '<div class="card mt-3"><div class="card-header"><strong>' . $product->name . '</strong> <small class="text-muted">(SKU: ' . $product->sku . ', Default Price: $' . number_format($product->front_sale_price, 2) . ')</small></div><div class="card-body"><div class="row">',
                ])
                ->add("products[{$product->id}][price]", 'number', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.price'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->price ?? '',
                    'attr' => [
                        'placeholder' => 'Leave empty for default price',
                        'step' => '0.01',
                        'min' => '0',
                        'class' => 'form-control',
                    ],
                    'wrapper' => ['class' => 'col-md-3'],
                ])
                ->add("products[{$product->id}][max_quantity]", 'number', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.max_quantity'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->max_quantity ?? '',
                    'attr' => [
                        'placeholder' => 'No limit',
                        'min' => '1',
                        'class' => 'form-control',
                    ],
                    'wrapper' => ['class' => 'col-md-3'],
                ])
                ->add("products[{$product->id}][deposit_amount]", 'number', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.deposit_amount'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->deposit_amount ?? '',
                    'attr' => [
                        'placeholder' => 'Override global deposit',
                        'step' => '0.01',
                        'min' => '0',
                        'class' => 'form-control',
                    ],
                    'wrapper' => ['class' => 'col-md-3'],
                ])
                ->add("products[{$product->id}][deposit_percentage]", 'number', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.deposit_percentage'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->deposit_percentage ?? '',
                    'attr' => [
                        'placeholder' => 'Override global %',
                        'step' => '0.01',
                        'min' => '0',
                        'max' => '100',
                        'class' => 'form-control',
                    ],
                    'wrapper' => ['class' => 'col-md-3'],
                ])
                ->add("product_section_end_{$product->id}", 'html', [
                    'html' => '</div><div class="row mt-2"><div class="col-md-6">',
                ])
                ->add("products[{$product->id}][is_active]", 'onOff', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.is_active'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->is_active ?? true,
                ])
                ->add("products[{$product->id}][pre_ordered]", 'number', [
                    'label' => trans('plugins/ecommerce::pre-orders.forms.pre_ordered'),
                    'label_attr' => ['class' => 'control-label'],
                    'value' => $pivot->pre_ordered ?? 0,
                    'attr' => [
                        'readonly' => true,
                        'min' => '0',
                        'class' => 'form-control',
                    ],
                    'wrapper' => ['class' => 'col-md-6'],
                ])
                ->add("product_section_close_{$product->id}", 'html', [
                    'html' => '</div></div></div></div>',
                ]);
            }
        }
    }
}
