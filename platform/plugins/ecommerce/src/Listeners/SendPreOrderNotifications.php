<?php

namespace Botble\Ecommerce\Listeners;

use Botble\Ecommerce\Events\PreOrderCreated;
use Botble\Ecommerce\Events\PreOrderDeliveryDateChanged;
use Botble\Ecommerce\Events\PreOrderStatusChanged;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Notifications\PreOrderConfirmationNotification;
use Botble\Ecommerce\Notifications\PreOrderDeliveryUpdateNotification;
use Botble\Ecommerce\Notifications\PreOrderStatusChangeNotification;
use Botble\Base\Facades\EmailHandler;
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

        // Also notify guests who pre-ordered using only email (no account)
        $guestContacts = $this->getGuestContactsForPreOrder($event->preOrder->getKey());
        if ($guestContacts->isNotEmpty()) {
            $product = $event->preOrder->products->first();
            foreach ($guestContacts as $contact) {
                if (! $product) {
                    continue;
                }
                Notification::route('mail', $contact['email'])->notify(
                    new PreOrderConfirmationNotification(
                        $event->preOrder,
                        $product,
                        1,
                        ['name' => $contact['name'], 'email' => $contact['email']]
                    )
                );
            }
        }

        // Send a brief admin email notification as well
        try {
            $mailer = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME);
            $subject = sprintf('New pre-order interest: %s', $event->preOrder->name);
            $content = sprintf(
                '<p>A new pre-order interest has been recorded for campaign <strong>%s</strong>.</p><p>Total products in campaign: %d</p>',
                e($event->preOrder->name),
                $event->preOrder->products->count()
            );
            $mailer->send($content, $subject);
        } catch (\Throwable $e) {
            // Fail silently; admin email is auxiliary
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
        $customerIds = PreOrderPayment::query()
            ->where('pre_order_id', $preOrder->getKey())
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique()
            ->values();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Customer::query()->whereIn('id', $customerIds)->get();
    }

    protected function getGuestContactsForPreOrder(int $preOrderId)
    {
        // Return distinct guest emails with optional names from payments
        return PreOrderPayment::query()
            ->where('pre_order_id', $preOrderId)
            ->whereNull('customer_id')
            ->whereNotNull('customer_email')
            ->get(['customer_email', 'customer_name'])
            ->unique('customer_email')
            ->map(function ($payment) {
                return [
                    'email' => $payment->customer_email,
                    'name' => $payment->customer_name ?: $payment->customer_email,
                ];
            });
    }
} 