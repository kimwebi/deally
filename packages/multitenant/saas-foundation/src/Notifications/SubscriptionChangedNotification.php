<?php

namespace SaasFoundation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Subscription;

class SubscriptionChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription,
        protected ?Plan $oldPlan,
        protected Plan $newPlan,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your subscription has been updated')
            ->greeting('Hello, '.$notifiable->name.'!')
            ->line('Your subscription has been updated to the "'.$this->newPlan->name.'" plan.');

        if ($this->oldPlan !== null) {
            $message->line('You were previously on the "'.$this->oldPlan->name.'" plan.');
        }

        $message
            ->line('Billing interval: '.$this->newPlan->billing_interval)
            ->action('View Subscription', route('dashboard'))
            ->line('Thank you for using our service!');

        return $message;
    }
}
