<?php

namespace Botble\Ecommerce\Tables;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Ecommerce\Models\PreOrder;
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
use Illuminate\Http\JsonResponse;

class PreOrderTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(PreOrder::class)
            ->addActions([
                EditAction::make()->route('pre-orders.edit'),
                DeleteAction::make()->route('pre-orders.destroy'),
            ]);
    }

    public function query(): Relation|Builder
    {
        $query = $this
            ->getModel()
            ->query()
            ->select([
                'id',
                'name',
                'pre_order_start_date',
                'pre_order_end_date',
                'expected_delivery_date',
                'status',
                'requires_deposit',
                'created_at',
            ])
            ->withCount('products')
            ->withCount('payments');

        return $this->applyScopes($query);
    }

    public function columns(): array
    {
        return [
            IdColumn::make(),
            NameColumn::make()->route('pre-orders.edit'),
            Column::make('products_count')
                ->title(trans('plugins/ecommerce::pre-orders.products_count'))
                ->alignCenter(),
            Column::make('payments_count')
                ->title(trans('plugins/ecommerce::pre-orders.payments_count'))
                ->alignCenter(),
            Column::make('pre_order_start_date')
                ->title(trans('plugins/ecommerce::pre-orders.start_date'))
                ->alignCenter()
                ->width(150),
            Column::make('pre_order_end_date')
                ->title(trans('plugins/ecommerce::pre-orders.end_date'))
                ->alignCenter()
                ->width(150),
            Column::make('expected_delivery_date')
                ->title(trans('plugins/ecommerce::pre-orders.delivery_date'))
                ->alignCenter()
                ->width(150),
            Column::make('requires_deposit')
                ->title(trans('plugins/ecommerce::pre-orders.requires_deposit'))
                ->alignCenter()
                ->width(120),
            StatusColumn::make(),
            CreatedAtColumn::make(),
        ];
    }

    public function buttons(): array
    {
        return $this->addCreateButton(route('pre-orders.create'), 'pre-orders.create');
    }

    public function bulkActions(): array
    {
        return [
            DeleteBulkAction::make()->permission('pre-orders.destroy'),
        ];
    }

    public function getBulkChanges(): array
    {
        return [
            'name' => [
                'title' => trans('core/base::tables.name'),
                'type' => 'text',
                'validate' => 'required|max:120',
            ],
            'status' => [
                'title' => trans('core/base::tables.status'),
                'type' => 'select',
                'choices' => BaseStatusEnum::labels(),
                'validate' => 'required|in:' . implode(',', BaseStatusEnum::values()),
            ],
            'created_at' => [
                'title' => trans('core/base::tables.created_at'),
                'type' => 'datePicker',
            ],
        ];
    }

} 