<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrderPayment $payment,
        public int $daysOverdue
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $paymentLink = route('public.pre-orders.pay', [
            'payment' => $this->payment->id,
            'token' => $this->generatePaymentToken($this->payment),
        ]);

        $autoCancelDate = $this->payment->payment_due_date 
            ? \Carbon\Carbon::parse($this->payment->payment_due_date)->addDays(7)->format('M d, Y')
            : __('Not set');

        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate('pre-order-overdue')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $this->payment->customer_name ?? $notifiable->name,
                'customer_email' => $this->payment->customer_email ?? $notifiable->email,
                'pre_order_name' => $this->payment->preOrder->name,
                'product_name' => $this->payment->product->name,
                'product_url' => $this->payment->product->url,
                'quantity' => $this->payment->quantity,
                'days_overdue' => $this->daysOverdue,
                'total_amount' => format_price($this->payment->total_amount),
                'paid_amount' => format_price($this->payment->paid_amount),
                'remaining_amount' => format_price($this->payment->remaining_amount),
                'payment_link' => $paymentLink,
                'payment_due_date' => $this->payment->payment_due_date 
                    ? $this->payment->payment_due_date->format('M d, Y') 
                    : __('Not set'),
                'auto_cancel_date' => $autoCancelDate,
                'expected_delivery_date' => $this->payment->preOrder->expected_delivery_date 
                    ? $this->payment->preOrder->expected_delivery_date->format('M d, Y') 
                    : __('To be announced'),
            ]);

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
    
    protected function generatePaymentToken(PreOrderPayment $payment): string
    {
        return hash_hmac('sha256', $payment->id . $payment->customer_email . $payment->created_at, config('app.key'));
    }
}
