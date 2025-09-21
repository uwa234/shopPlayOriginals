<?php

namespace Botble\Ecommerce\Listeners;

use Botble\Ecommerce\Events\PreOrderCreated;
use Botble\Ecommerce\Events\PreOrderDeliveryDateChanged;
use Botble\Ecommerce\Events\PreOrderStatusChanged;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Notifications\PreOrderConfirmationNotification;
use Botble\Ecommerce\Notifications\PreOrderDeliveryUpdateNotification;
use Botble\Ecommerce\Notifications\PreOrderStatusChangeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendPreOrderNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle($event): void
    {
        match (get_class($event)) {
            PreOrderCreated::class => $this->handlePreOrderCreated($event),
            PreOrderStatusChanged::class => $this->handlePreOrderStatusChanged($event),
            PreOrderDeliveryDateChanged::class => $this->handlePreOrderDeliveryDateChanged($event),
            default => null,
        };
    }

    protected function handlePreOrderCreated(PreOrderCreated $event): void
    {
        // Get customers who have pre-ordered products from this pre-order
        $customers = $this->getCustomersForPreOrder($event->preOrder);

        foreach ($customers as $customer) {
            // You would get the specific product and quantity from the order/cart context
            // For now, we'll use the first product as an example
            $product = $event->preOrder->products->first();
            if ($product) {
                $customer->notify(new PreOrderConfirmationNotification(
                    $event->preOrder,
                    $product,
                    1, // quantity - would come from actual order
                    ['name' => $customer->name, 'email' => $customer->email]
                ));
            }
        }
    }

    protected function handlePreOrderStatusChanged(PreOrderStatusChanged $event): void
    {
        $customers = $this->getCustomersForPreOrder($event->preOrder);

        foreach ($customers as $customer) {
            $customer->notify(new PreOrderStatusChangeNotification(
                $event->preOrder,
                $event->oldStatus,
                $event->newStatus
            ));
        }
    }

    protected function handlePreOrderDeliveryDateChanged(PreOrderDeliveryDateChanged $event): void
    {
        $customers = $this->getCustomersForPreOrder($event->preOrder);

        foreach ($customers as $customer) {
            $customer->notify(new PreOrderDeliveryUpdateNotification(
                $event->preOrder,
                $event->oldDeliveryDate,
                $event->newDeliveryDate
            ));
        }
    }

    protected function getCustomersForPreOrder($preOrder)
    {
        // This would typically get customers who have pre-ordered products
        // from this pre-order campaign. For now, return empty collection
        // In a real implementation, you'd have a pre_orders_customers table
        // or get this from orders/cart data
        return collect();
    }
} 