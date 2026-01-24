<?php

namespace Botble\Ecommerce\Commands;

use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Notifications\PreOrderOverdueNotification;
use Botble\Ecommerce\Services\PreOrderPaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('cms:pre-orders:process-deadlines', 'Process overdue pre-order payments and send notifications')]
class ProcessPreOrderDeadlinesCommand extends Command
{
    public function handle(): int
    {
        $today = Carbon::today();
        $count = 0;
        $autoCancelDays = 7; // Configurable: auto-cancel after X days overdue

        // Find payments where payment_due_date < today and remaining_amount > 0
        $overduePayments = PreOrderPayment::query()
            ->where('payment_status', 'paid')
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('payment_due_date')
            ->whereDate('payment_due_date', '<', $today)
            ->whereNotIn('payment_status', ['cancelled', 'refunded'])
            ->with(['customer', 'product', 'preOrder'])
            ->get();

        $preOrderPaymentService = app(PreOrderPaymentService::class);

        foreach ($overduePayments as $payment) {
            if (!$payment->payment_due_date) {
                continue;
            }

            $dueDate = Carbon::parse($payment->payment_due_date);
            $daysOverdue = $today->diffInDays($dueDate);

            // Check if already marked as overdue
            if ($payment->payment_status !== 'overdue') {
                // Mark as overdue
                $preOrderPaymentService->updateStatus(
                    $payment,
                    'overdue',
                    __('Payment is overdue by :days day(s)', ['days' => $daysOverdue])
                );

                // Send overdue notification
                try {
                    if ($payment->customer) {
                        Notification::send($payment->customer, new PreOrderOverdueNotification($payment, $daysOverdue));
                    } elseif ($payment->customer_email) {
                        Notification::route('mail', $payment->customer_email)
                            ->notify(new PreOrderOverdueNotification($payment, $daysOverdue));
                    }
                } catch (Throwable $exception) {
                    $this->error('Failed to send overdue notification for payment ID ' . $payment->id . ': ' . $exception->getMessage());
                }
            }

            // Auto-cancel if overdue for more than X days
            if ($daysOverdue >= $autoCancelDays) {
                try {
                    $preOrderPaymentService->cancelPreOrder(
                        $payment,
                        'overdue',
                        __('Auto-cancelled: Payment overdue for more than :days days', ['days' => $autoCancelDays])
                    );
                    $this->info('Auto-cancelled payment ID ' . $payment->id . ' (overdue for ' . $daysOverdue . ' days)');
                } catch (Throwable $exception) {
                    $this->error('Failed to auto-cancel payment ID ' . $payment->id . ': ' . $exception->getMessage());
                }
            }

            $count++;
        }

        if ($count > 0) {
            $this->info('Processed ' . $count . ' overdue payment' . ($count != 1 ? 's' : '') . '.');
        } else {
            $this->info('No overdue payments to process.');
        }

        return self::SUCCESS;
    }
}
