<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelled extends Notification
{
    use Queueable;

    protected $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct($subscription)
    {
        $this->subscription = $subscription;
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
            ->subject('Subscription Cancelled')
            ->line('Your subscription to the ' . $this->subscription->name . ' plan has been cancelled.')
            ->action('View Subscription', url('/subscription'))
            ->line('We hope to see you back soon.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->name,
            'status' => $this->subscription->stripe_status,
            'ended_at' => $this->subscription->ended_at?->toDateTimeString(),
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
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->name,
            'status' => $this->subscription->stripe_status,
            'ended_at' => $this->subscription->ended_at?->toDateTimeString(),
            'type' => 'subscription.cancelled',
        ];
    }
}