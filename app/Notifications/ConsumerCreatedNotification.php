<?php

namespace App\Notifications;

use App\Models\SalesCase;
use Illuminate\Notifications\Notification;

class ConsumerCreatedNotification extends Notification
{
    public function __construct(public SalesCase $salesCase) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'consumer.created',
            'title' => 'Konsumen baru',
            'message' => $this->salesCase->consumer->nama_konsumen.' ditambahkan pada '.$this->salesCase->lot->code.'.',
            'url' => route('consumers.show', $this->salesCase),
        ];
    }
}
