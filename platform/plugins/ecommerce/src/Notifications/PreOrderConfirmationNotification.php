<?php

namespace Botble\Ecommerce\Notifications;

use Botble\Base\Facades\EmailHandler;
use Botble\Ecommerce\Models\PreOrder;
use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class PreOrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PreOrder $preOrder,
        public Product $product,
        public int $quantity,
        public array $customerData,
        public ?PreOrderPayment $payment = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $paymentDetails = [];
        $paymentLink = null;
        
        if ($this->payment) {
            $paymentDetails = [
                'payment_type' => $this->payment->isDeposit ? 'Deposit' : 'Full Payment',
                'total_amount' => format_price($this->payment->total_amount),
                'paid_amount' => format_price($this->payment->paid_amount),
                'remaining_amount' => format_price($this->payment->remaining_amount),
            ];
            
            // Generate payment link if there's a remaining balance
            if ($this->payment->isDeposit && $this->payment->remaining_amount > 0) {
                $paymentLink = route('public.pre-orders.pay', [
                    'payment' => $this->payment->id,
                    'token' => $this->generatePaymentToken($this->payment),
                ]);
            }
        }

        $emailHandler = EmailHandler::setModule(ECOMMERCE_MODULE_SCREEN_NAME)
            ->setType('plugins')
            ->setTemplate('pre-order-confirmation')
            ->addTemplateSettings(ECOMMERCE_MODULE_SCREEN_NAME, config('plugins.ecommerce.email', []))
            ->setVariableValues(array_merge([
                'customer_name' => $this->customerData['name'] ?? $notifiable->name,
                'customer_email' => $this->customerData['email'] ?? $notifiable->email,
                'pre_order_name' => $this->preOrder->name,
                'product_name' => $this->product->name,
                'product_url' => $this->product->url,
                'quantity' => $this->quantity,
                'expected_delivery_date' => $this->preOrder->expected_delivery_date->format('M d, Y'),
                'pre_order_end_date' => $this->preOrder->pre_order_end_date->format('M d, Y'),
                'order_date' => now()->format('M d, Y'),
            ], $paymentDetails, [
                'payment_link' => $paymentLink,
                'has_remaining_balance' => $this->payment && $this->payment->isDeposit && $this->payment->remaining_amount > 0,
            ]));

        return (new MailMessage())
            ->view(['html' => new HtmlString($emailHandler->getContent())])
            ->subject($emailHandler->getSubject());
    }
    
    protected function generatePaymentToken(PreOrderPayment $payment): string
    {
        // Generate a secure token for payment link
        return hash_hmac('sha256', $payment->id . $payment->customer_email . $payment->created_at, config('app.key'));
    }
} 