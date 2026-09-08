<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject("Potvrda narudžbe {$this->order->order_reference}")
            ->greeting("Pozdrav {$this->order->customer_name},")
            ->line('Hvala na narudžbi! Zaprimili smo je i uskoro je šaljemo dobavljaču.')
            ->line("Broj narudžbe: {$this->order->order_reference}")
            ->line('Ukupan iznos: '.number_format((float) $this->order->total_price, 2).' EUR')
            ->line('Procijenjena dostava: '.$this->order->estimatedDelivery()->toDateString())
            ->line('Status narudžbe možete pratiti pomoću broja narudžbe i svoje email adrese.');
    }
}
