<?php

namespace Botble\Ecommerce\Tables;

use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\Action;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\Columns\Column;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\NameColumn;
use Botble\Table\Columns\StatusColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class PreOrderPaymentTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(PreOrderPayment::class)
            ->addActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('ti ti-eye')
                    ->color('primary')
                    ->url(function (Action $action) {
                        $payment = $action->getItem();
                        return route('pre-orders.payments.show', [
                            'preOrder' => $payment->pre_order_id,
                            'payment' => $payment->getKey()
                        ]);
                    })
                    ->permission('pre-orders.edit'),
                DeleteAction::make()->route('pre-orders.payments.destroy'),
            ]);
    }

    public function ajax(): JsonResponse
    {
        $data = $this->table
            ->eloquent($this->query())
            ->editColumn('product_id', function ($item) {
                try {
                    if (is_object($item) && isset($item->product) && $item->product) {
                        return $item->product->name;
                    }
                    return '&mdash;';
                } catch (\Throwable $e) {
                    return '&mdash;';
                }
            })
            ->editColumn('total_amount', function ($item) {
                try {
                    if (is_object($item) && isset($item->total_amount)) {
                        return format_price($item->total_amount);
                    }
                    return '&mdash;';
                } catch (\Throwable $e) {
                    return '&mdash;';
                }
            })
            ->editColumn('paid_amount', function ($item) {
                try {
                    if (is_object($item) && isset($item->paid_amount)) {
                        return format_price($item->paid_amount);
                    }
                    return '&mdash;';
                } catch (\Throwable $e) {
                    return '&mdash;';
                }
            })
            ->editColumn('remaining_amount', function ($item) {
                try {
                    if (is_object($item) && isset($item->remaining_amount)) {
                        return format_price($item->remaining_amount);
                    }
                    return '&mdash;';
                } catch (\Throwable $e) {
                    return '&mdash;';
                }
            })
            ->editColumn('payment_type', function ($item) {
                try {
                    if (is_object($item) && isset($item->payment_type)) {
                        $type = $item->payment_type;
                        if ($type instanceof \Botble\Ecommerce\Enums\PreOrderPaymentTypeEnum) {
                            return $type->label();
                        }
                        return $type === 'deposit' ? __('Deposit') : __('Full Payment');
                    }
                    return '&mdash;';
                } catch (\Throwable $e) {
                    return '&mdash;';
                }
            });

        return $this->toJson($data);
    }

    public function query(): Relation|Builder
    {
        $query = $this
            ->getModel()
            ->query()
            ->select([
                'id',
                'pre_order_id',
                'product_id',
                'customer_id',
                'customer_name',
                'customer_email',
                'quantity',
                'product_price',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'payment_type',
                'payment_status',
                'payment_method',
                'payment_due_date',
                'created_at',
            ])
            ->with(['product', 'customer']);

        // Filter by pre_order_id if provided in route parameter
        if ($this->request()->route('preOrder')) {
            $preOrder = $this->request()->route('preOrder');
            $preOrderId = is_object($preOrder) ? $preOrder->id : $preOrder;
            $query->where('pre_order_id', $preOrderId);
        }

        // Filter for overdue payments if requested
        if ($this->request()->has('filter_overdue') && $this->request()->input('filter_overdue') === '1') {
            $query->where('payment_status', 'paid')
                  ->where('remaining_amount', '>', 0)
                  ->whereNotNull('payment_due_date')
                  ->whereDate('payment_due_date', '<', now());
        }

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            IdColumn::make(),
            Column::make('customer_name')
                ->title(__('Customer Name'))
                ->alignLeft(),
            Column::make('customer_email')
                ->title(__('Customer Email'))
                ->alignLeft(),
            Column::make('product_id')
                ->title(__('Product'))
                ->alignLeft(),
            Column::make('quantity')
                ->title(__('Quantity'))
                ->alignCenter(),
            Column::make('total_amount')
                ->title(__('Total Amount'))
                ->alignRight(),
            Column::make('paid_amount')
                ->title(__('Paid Amount'))
                ->alignRight(),
            Column::make('remaining_amount')
                ->title(__('Remaining Amount'))
                ->alignRight(),
            Column::make('payment_type')
                ->title(__('Payment Type'))
                ->alignCenter(),
            StatusColumn::make('payment_status')
                ->title(__('Payment Status')),
            CreatedAtColumn::make(),
        ];
    }

    public function bulkActions(): array
    {
        return [
            DeleteBulkAction::make()->permission('pre-orders.payments.destroy'),
        ];
    }
}
