<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    use Queueable;

    protected $invoice;

    /**
     * Create a new notification instance.
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->line('Your invoice #' . $this->invoice->number . ' has been paid successfully.')
            ->action('View Invoice', url('/invoice/' . $this->invoice->id))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'amount' => $this->invoice->total,
            'currency' => $this->invoice->currency,
            'paid_at' => $this->invoice->paid_at->toDateTimeString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toBroadcast(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'amount' => $this->invoice->total,
            'currency' => $this->invoice->currency,
            'paid_at' => $this->invoice->paid_at->toDateTimeString(),
            'type' => 'invoice.paid',
        ];
    }
}