<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrder $preOrder,
        public Product $product,
        public int $quantity,
        public array $customerData
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
            ->setTemplate('pre-order-confirmation')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $this->customerData['name'] ?? $notifiable->name,
                'customer_email' => $this->customerData['email'] ?? $notifiable->email,
                'pre_order_name' => $this->preOrder->name,
                'product_name' => $this->product->name,
                'product_url' => $this->product->url,
                'quantity' => $this->quantity,
                'expected_delivery_date' => $this->preOrder->expected_delivery_date->format('M d, Y'),
                'pre_order_end_date' => $this->preOrder->pre_order_end_date->format('M d, Y'),
                'order_date' => now()->format('M d, Y'),
            ]);

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
} 