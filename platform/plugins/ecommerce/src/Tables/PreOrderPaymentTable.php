<?php

namespace Botble\Ecommerce\Tables;

use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Table\Abstracts\TableAbstract;
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

class PreOrderPaymentTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(PreOrderPayment::class)
            ->addActions([
                EditAction::make()->route('pre-orders.payments.show'),
                DeleteAction::make()->route('pre-orders.payments.destroy'),
            ]);
    }

    public function query(): Relation|Builder
    {
        $query = $this
            ->getModel()
            ->query()
            ->select([
                'id',
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
                'created_at',
            ])
            ->with(['product', 'customer']);

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            IdColumn::make(),
            Column::make('customer_name')
                ->title(trans('plugins/ecommerce::pre-orders.payments.customer_name'))
                ->alignLeft(),
            Column::make('customer_email')
                ->title(trans('plugins/ecommerce::pre-orders.payments.customer_email'))
                ->alignLeft(),
            Column::make('product.name')
                ->title(trans('plugins/ecommerce::pre-orders.payments.product'))
                ->alignLeft(),
            Column::make('quantity')
                ->title(trans('plugins/ecommerce::pre-orders.payments.quantity'))
                ->alignCenter(),
            Column::make('total_amount')
                ->title(trans('plugins/ecommerce::pre-orders.payments.total_amount'))
                ->alignRight(),
            Column::make('paid_amount')
                ->title(trans('plugins/ecommerce::pre-orders.payments.paid_amount'))
                ->alignRight(),
            Column::make('remaining_amount')
                ->title(trans('plugins/ecommerce::pre-orders.payments.remaining_amount'))
                ->alignRight(),
            Column::make('payment_type')
                ->title(trans('plugins/ecommerce::pre-orders.payments.payment_type'))
                ->alignCenter(),
            StatusColumn::make('payment_status')
                ->title(trans('plugins/ecommerce::pre-orders.payments.payment_status')),
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
