<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderStatusChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrder $preOrder,
        public string $oldStatus,
        public string $newStatus,
        public ?string $message = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $templateName = match ($this->newStatus) {
            'ready_for_pickup' => 'pre-order-ready-for-pickup',
            'cancelled' => 'pre-order-cancelled',
            'completed' => 'pre-order-completed',
            default => 'pre-order-status-update',
        };

        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate($templateName)
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $notifiable->name,
                'customer_email' => $notifiable->email,
                'pre_order_name' => $this->preOrder->name,
                'old_status' => ucwords(str_replace('_', ' ', $this->oldStatus)),
                'new_status' => ucwords(str_replace('_', ' ', $this->newStatus)),
                'status_message' => $this->message,
                'expected_delivery_date' => $this->preOrder->expected_delivery_date->format('M d, Y'),
                'update_date' => now()->format('M d, Y'),
                'is_ready' => $this->newStatus === 'ready_for_pickup',
                'is_cancelled' => $this->newStatus === 'cancelled',
                'is_completed' => $this->newStatus === 'completed',
            ]);

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
} 