<?php

namespace App\Notifications;

use App\Models\SalesAgenda;
use Illuminate\Notifications\Notification;

class SalesAgendaAssignmentNotification extends Notification
{
    public function __construct(
        public SalesAgenda $agenda,
        public string $action,
        public ?string $actorName = null,
        public ?string $otherOwnerName = null,
        public ?string $reason = null,
    ) {}

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
            'type' => 'sales-agenda.'.$this->action,
            'title' => match ($this->action) {
                'created_for' => 'Agenda baru ditugaskan',
                'redirected_new_owner' => 'Agenda dialihkan kepada Anda',
                'redirected_previous_owner' => 'Agenda Anda dialihkan',
                'cancelled' => 'Agenda dibatalkan',
                default => 'Perubahan agenda',
            },
            'message' => match ($this->action) {
                'created_for' => 'Agenda "'.$this->agenda->title.'" ditugaskan kepada Anda oleh '.($this->actorName ?? 'koordinator').'.',
                'redirected_new_owner' => 'Agenda "'.$this->agenda->title.'" dialihkan kepada Anda oleh '.($this->actorName ?? 'koordinator').'.',
                'redirected_previous_owner' => 'Agenda "'.$this->agenda->title.'" dialihkan kepada '.($this->otherOwnerName ?? 'Sales lain').' oleh '.($this->actorName ?? 'koordinator').'.',
                'cancelled' => 'Agenda "'.$this->agenda->title.'" dibatalkan oleh '.($this->actorName ?? 'koordinator').'.',
                default => 'Agenda "'.$this->agenda->title.'" mengalami perubahan.',
            },
            'reason' => $this->reason,
            'url' => route('sales-agendas.show', $this->agenda),
        ];
    }
}
