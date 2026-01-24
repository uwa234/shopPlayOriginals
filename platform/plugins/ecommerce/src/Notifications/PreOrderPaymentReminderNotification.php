<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrderPayment $payment,
        public string $reminderType = 'due_date'
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $reminderMessages = [
            '7_days_before' => __('Your pre-order balance payment is due in 7 days.'),
            '3_days_before' => __('Your pre-order balance payment is due in 3 days.'),
            'due_date' => __('Your pre-order balance payment is due today.'),
            '3_days_after' => __('Your pre-order balance payment is overdue by 3 days.'),
        ];

        $reminderMessage = $reminderMessages[$this->reminderType] ?? __('Your pre-order balance payment reminder.');

        $paymentLink = route('public.pre-orders.pay', [
            'payment' => $this->payment->id,
            'token' => $this->generatePaymentToken($this->payment),
        ]);

        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate('pre-order-payment-reminder')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $this->payment->customer_name ?? $notifiable->name,
                'customer_email' => $this->payment->customer_email ?? $notifiable->email,
                'pre_order_name' => $this->payment->preOrder->name,
                'product_name' => $this->payment->product->name,
                'product_url' => $this->payment->product->url,
                'quantity' => $this->payment->quantity,
                'reminder_message' => $reminderMessage,
                'reminder_type' => $this->reminderType,
                'total_amount' => format_price($this->payment->total_amount),
                'paid_amount' => format_price($this->payment->paid_amount),
                'remaining_amount' => format_price($this->payment->remaining_amount),
                'payment_link' => $paymentLink,
                'payment_due_date' => $this->payment->payment_due_date 
                    ? $this->payment->payment_due_date->format('M d, Y') 
                    : __('Not set'),
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
