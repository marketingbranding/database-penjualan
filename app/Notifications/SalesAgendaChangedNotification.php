<?php

namespace App\Notifications;

use App\Models\SalesAgenda;
use Illuminate\Notifications\Notification;

class SalesAgendaChangedNotification extends Notification
{
    public function __construct(public SalesAgenda $agenda, public string $actorName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'daily-plan.agenda-changed',
            'title' => 'Agenda kerja diperbarui',
            'message' => 'Agenda kerja Anda hari ini diperbarui oleh Koordinator '.$this->actorName.'.',
            'url' => route('daily-plans.show', $this->agenda->daily_plan_id),
        ];
    }
}
