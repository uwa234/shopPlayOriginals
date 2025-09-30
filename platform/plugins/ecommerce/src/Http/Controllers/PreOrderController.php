<?php

namespace Botble\Ecommerce\Http\Controllers;

use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Events\DeletedContentEvent;
use Botble\Base\Events\UpdatedContentEvent;
use Botble\Base\Facades\PageTitle;
use Botble\Base\Http\Actions\DeleteResourceAction;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Forms\PreOrderForm;
use Botble\Ecommerce\Http\Requests\PreOrderRequest;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Tables\PreOrderTable;
use Illuminate\Http\Request;

class PreOrderController extends BaseController
{
    public function index(PreOrderTable $table)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.name'));

        return $table->renderTable();
    }

    public function create()
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.create'));

        return PreOrderForm::create()->renderForm();
    }

    public function store(PreOrderRequest $request)
    {
        $preOrder = PreOrder::query()->create($request->input());

        if ($request->input('products')) {
            $products = [];
            foreach ($request->input('products') as $productId => $productData) {
                $products[$productId] = [
                    'price' => $productData['price'] ?? 0,
                    'max_quantity' => $productData['max_quantity'] ?? null,
                    'pre_ordered' => 0,
                    'is_active' => isset($productData['is_active']) ? (bool)$productData['is_active'] : true,
                    'deposit_amount' => $productData['deposit_amount'] ?? null,
                    'deposit_percentage' => $productData['deposit_percentage'] ?? null,
                ];
            }
            $preOrder->products()->sync($products);
        }

        event(new CreatedContentEvent(PREORDER_MODULE_SCREEN_NAME, $request, $preOrder));

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('pre-orders.index'))
            ->setNextUrl(route('pre-orders.edit', $preOrder->getKey()))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function show(PreOrder $preOrder)
    {
        PageTitle::setTitle($preOrder->name);

        return view('plugins/ecommerce::pre-orders.show', compact('preOrder'));
    }

    public function edit(PreOrder $preOrder)
    {
        PageTitle::setTitle(trans('plugins/ecommerce::pre-orders.edit') . ' "' . $preOrder->name . '"');

        return PreOrderForm::createFromModel($preOrder)->renderForm();
    }

    public function update(PreOrder $preOrder, PreOrderRequest $request)
    {
        $preOrder->fill($request->input());
        $preOrder->save();

        if ($request->input('products')) {
            $products = [];
            foreach ($request->input('products') as $productId => $productData) {
                $products[$productId] = [
                    'price' => $productData['price'] ?? 0,
                    'max_quantity' => $productData['max_quantity'] ?? null,
                    'pre_ordered' => $productData['pre_ordered'] ?? 0,
                    'is_active' => isset($productData['is_active']) ? (bool)$productData['is_active'] : true,
                    'deposit_amount' => $productData['deposit_amount'] ?? null,
                    'deposit_percentage' => $productData['deposit_percentage'] ?? null,
                ];
            }
            $preOrder->products()->sync($products);
        }

        event(new UpdatedContentEvent(PREORDER_MODULE_SCREEN_NAME, $request, $preOrder));

        return $this
            ->httpResponse()
            ->setPreviousUrl(route('pre-orders.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(PreOrder $preOrder)
    {
        try {
            $preOrder->delete();

            event(new DeletedContentEvent(PREORDER_MODULE_SCREEN_NAME, request(), $preOrder));

            return $this
                ->httpResponse()
                ->setMessage(trans('core/base::notices.delete_success_message'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function deletes(Request $request)
    {
        return DeleteResourceAction::make(PreOrder::class);
    }
} 