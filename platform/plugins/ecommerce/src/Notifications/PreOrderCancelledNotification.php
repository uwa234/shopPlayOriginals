<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrderPayment $payment,
        public ?string $reason = null,
        public ?string $reasonDescription = null
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
            ->setTemplate('pre-order-cancelled')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $this->payment->customer_name ?? $notifiable->name,
                'customer_email' => $this->payment->customer_email ?? $notifiable->email,
                'pre_order_name' => $this->payment->preOrder->name,
                'product_name' => $this->payment->product->name,
                'product_url' => $this->payment->product->url,
                'quantity' => $this->payment->quantity,
                'total_amount' => format_price($this->payment->total_amount),
                'paid_amount' => format_price($this->payment->paid_amount),
                'refunded_amount' => format_price($this->payment->refunded_amount ?? 0),
                'cancellation_reason' => $this->reason ?? $this->payment->cancellation_reason ?? __('Not specified'),
                'cancellation_reason_description' => $this->reasonDescription ?? $this->payment->cancellation_reason_description,
                'cancelled_at' => now()->format('M d, Y H:i'),
                'has_refund' => ($this->payment->refunded_amount ?? 0) > 0,
            ]);

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
}
