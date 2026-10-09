<?php

namespace App\Notifications;

use App\Models\SalesCase;
use Illuminate\Notifications\Notification;

class ReservationCancelledNotification extends Notification
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
            'type' => 'reservation.cancelled',
            'title' => 'Reservasi dibatalkan',
            'message' => 'Reservasi '.$this->salesCase->lot->project->name.'-'.$this->salesCase->lot->code.' untuk '.$this->salesCase->consumer->nama_konsumen.' dibatalkan.',
            'url' => route('consumers.show', $this->salesCase),
        ];
    }
}
