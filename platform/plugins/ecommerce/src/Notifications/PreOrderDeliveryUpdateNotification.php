<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderDeliveryUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrder $preOrder,
        public Carbon $oldDeliveryDate,
        public Carbon $newDeliveryDate
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate('pre-order-delivery-update')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $notifiable->name,
                'customer_email' => $notifiable->email,
                'pre_order_name' => $this->preOrder->name,
                'old_delivery_date' => $this->oldDeliveryDate->format('M d, Y'),
                'new_delivery_date' => $this->newDeliveryDate->format('M d, Y'),
                'update_date' => now()->format('M d, Y'),
                'is_delayed' => $this->newDeliveryDate->gt($this->oldDeliveryDate),
                'is_earlier' => $this->newDeliveryDate->lt($this->oldDeliveryDate),
            ]);

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
} 