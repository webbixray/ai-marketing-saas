<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $notifiable->email_verification_token;

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Thank you for registering. Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This link will expire in 24 hours.')
            ->line('If you did not create an account, no further action is required.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'email_verification',
            'message' => 'Please verify your email address.',
            'token' => $notifiable->email_verification_token,
        ];
    }
}
