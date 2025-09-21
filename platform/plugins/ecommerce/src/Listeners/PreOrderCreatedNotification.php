<?php

namespace Botble\Ecommerce\Listeners;

use Botble\Base\Events\AdminNotificationEvent;
use Botble\Base\Supports\AdminNotificationItem;
use Botble\Ecommerce\Events\PreOrderCreated;

class PreOrderCreatedNotification
{
    public function handle(PreOrderCreated $event): void
    {
        event(new AdminNotificationEvent(
            AdminNotificationItem::make()
                ->title(trans('plugins/ecommerce::pre-orders.notifications.new_pre_order'))
                ->description(trans('plugins/ecommerce::pre-orders.notifications.new_pre_order_description', [
                    'pre_order_name' => $event->preOrder->name,
                    'product_count' => $event->preOrder->products->count(),
                ]))
                ->action(trans('plugins/ecommerce::pre-orders.notifications.view'), route('pre-orders.edit', $event->preOrder->getKey()))
        ));
    }
} 