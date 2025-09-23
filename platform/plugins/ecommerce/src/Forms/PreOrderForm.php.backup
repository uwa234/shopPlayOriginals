<?php

namespace Botble\Ecommerce\Forms;

use Botble\Base\Forms\FormAbstract;
use Botble\Ecommerce\Http\Requests\PreOrderRequest;
use Botble\Ecommerce\Models\PreOrder;

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
            ->add('pre_order_start_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.start_date'),
                'label_attr' => ['class' => 'control-label required'],
            ])
            ->add('pre_order_end_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.end_date'),
                'label_attr' => ['class' => 'control-label required'],
            ])
            ->add('expected_delivery_date', 'datePicker', [
                'label' => trans('plugins/ecommerce::pre-orders.forms.delivery_date'),
                'label_attr' => ['class' => 'control-label'],
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
                'choices' => [
                    'active' => trans('core/base::tables.active'),
                    'inactive' => trans('core/base::tables.inactive'),
                ],
            ])
            ->setBreakFieldPoint('status');
    }
}
