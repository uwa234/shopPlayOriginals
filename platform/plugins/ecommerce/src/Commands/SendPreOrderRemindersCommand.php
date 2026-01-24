<?php

namespace Botble\Ecommerce\Commands;

use Botble\Ecommerce\Models\PreOrderPayment;
use Botble\Ecommerce\Notifications\PreOrderPaymentReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand('cms:pre-orders:send-reminders', 'Send reminder emails for pre-order balance payments')]
class SendPreOrderRemindersCommand extends Command
{
    public function handle(): int
    {
        $today = Carbon::today();
        $count = 0;

        // Find payments with remaining balance that are paid (deposit paid)
        $payments = PreOrderPayment::query()
            ->where('payment_status', 'paid')
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('payment_due_date')
            ->with(['customer', 'product', 'preOrder'])
            ->get();

        foreach ($payments as $payment) {
            if (!$payment->payment_due_date) {
                continue;
            }

            $dueDate = Carbon::parse($payment->payment_due_date);
            $daysUntilDue = $today->diffInDays($dueDate, false);
            $reminderType = null;

            // Determine reminder type based on days until due date
            if ($daysUntilDue === 7) {
                $reminderType = '7_days_before';
            } elseif ($daysUntilDue === 3) {
                $reminderType = '3_days_before';
            } elseif ($daysUntilDue === 0) {
                $reminderType = 'due_date';
            } elseif ($daysUntilDue === -3) {
                $reminderType = '3_days_after';
            }

            if (!$reminderType) {
                continue;
            }

            // Check if reminder was already sent for this type
            $paymentData = $payment->payment_data ?? [];
            $remindersSent = $paymentData['reminders_sent'] ?? [];
            
            if (in_array($reminderType, $remindersSent)) {
                continue; // Already sent this reminder
            }

            try {
                // Send notification
                if ($payment->customer) {
                    Notification::send($payment->customer, new PreOrderPaymentReminderNotification($payment, $reminderType));
                } elseif ($payment->customer_email) {
                    Notification::route('mail', $payment->customer_email)
                        ->notify(new PreOrderPaymentReminderNotification($payment, $reminderType));
                }

                // Track reminder in payment_data
                $remindersSent[] = $reminderType;
                $paymentData['reminders_sent'] = $remindersSent;
                $payment->update(['payment_data' => $paymentData]);

                $count++;
            } catch (Throwable $exception) {
                $this->error('Failed to send reminder for payment ID ' . $payment->id . ': ' . $exception->getMessage());
                continue;
            }
        }

        if ($count > 0) {
            $this->info('Sent ' . $count . ' reminder email' . ($count != 1 ? 's' : '') . ' successfully!');
        } else {
            $this->info('No reminders to send at this time.');
        }

        return self::SUCCESS;
    }
}
