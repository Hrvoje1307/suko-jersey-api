<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Nova informacija o narudžbi {$this->order->order_reference}")
            ->greeting("Pozdrav {$this->order->customer_name},")
            ->line("Status vaše narudžbe {$this->order->order_reference} je promijenjen.")
            ->line('Novi status: '.$this->order->status->label());

        if ($this->order->status->exposesTracking() && $this->order->tracking_number_internal) {
            $message->line('Broj za praćenje: '.$this->order->tracking_number_internal);
        }

        return $message->line('Procijenjena dostava: '.$this->order->estimatedDelivery()->toDateString());
    }
}
