<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\PreOrderPaymentStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class PreOrderStatusUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrderPayment $payment,
        public PreOrderPaymentStatusHistory $statusHistory
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statusLabels = [
            'pending' => __('Pending'),
            'paid' => __('Paid'),
            'partially_paid' => __('Partially Paid'),
            'failed' => __('Failed'),
            'refunded' => __('Refunded'),
            'partially_refunded' => __('Partially Refunded'),
            'cancelled' => __('Cancelled'),
            'processing' => __('Processing'),
            'shipped' => __('Shipped'),
            'delivered' => __('Delivered'),
            'overdue' => __('Overdue'),
        ];

        $statusLabel = $statusLabels[$this->statusHistory->status] ?? ucfirst($this->statusHistory->status);
        
        $paymentLink = null;
        if ($this->payment->isDeposit && $this->payment->remaining_amount > 0 && $this->statusHistory->status === 'paid') {
            $paymentLink = route('public.pre-orders.pay', [
                'payment' => $this->payment->id,
                'token' => $this->generatePaymentToken($this->payment),
            ]);
        }

        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate('pre-order-status-update')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues([
                'customer_name' => $this->payment->customer_name ?? $notifiable->name,
                'customer_email' => $this->payment->customer_email ?? $notifiable->email,
                'pre_order_name' => $this->payment->preOrder->name,
                'product_name' => $this->payment->product->name,
                'product_url' => $this->payment->product->url,
                'quantity' => $this->payment->quantity,
                'status' => $statusLabel,
                'status_description' => $this->statusHistory->description,
                'total_amount' => format_price($this->payment->total_amount),
                'paid_amount' => format_price($this->payment->paid_amount),
                'remaining_amount' => format_price($this->payment->remaining_amount),
                'payment_link' => $paymentLink,
                'has_remaining_balance' => $this->payment->remaining_amount > 0,
                'expected_delivery_date' => $this->payment->preOrder->expected_delivery_date 
                    ? $this->payment->preOrder->expected_delivery_date->format('M d, Y') 
                    : __('To be announced'),
                'updated_at' => $this->statusHistory->created_at->format('M d, Y H:i'),
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
