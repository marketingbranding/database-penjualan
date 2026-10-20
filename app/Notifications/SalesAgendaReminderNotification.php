<?php

namespace App\Notifications;

use App\Models\SalesAgenda;
use App\Support\BusinessTime;
use Illuminate\Notifications\Notification;

class SalesAgendaReminderNotification extends Notification
{
    public function __construct(public SalesAgenda $agenda, public string $reminderType) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $start = BusinessTime::format($this->agenda->planned_start_at, 'H:i');
        $end = BusinessTime::format($this->agenda->planned_end_at, 'H:i');

        return [
            'type' => 'sales-agenda.reminder.'.$this->reminderType,
            'title' => match ($this->reminderType) {
                'upcoming' => 'Agenda berikutnya',
                'starting_now' => 'Agenda dimulai sekarang',
                'ending_soon' => 'Agenda segera selesai',
                'overdue' => 'Agenda melewati waktu',
                default => 'Pengingat agenda',
            },
            'message' => match ($this->reminderType) {
                'upcoming' => 'Agenda berikutnya dimulai 15 menit lagi: '.$this->agenda->title.' — '.$start.'.',
                'starting_now' => 'Sekarang: '.$this->agenda->title.' — '.$start.'–'.$end.'.',
                'ending_soon' => $this->agenda->title.' selesai 15 menit lagi.',
                'overdue' => 'Agenda '.$this->agenda->title.' sudah melewati waktu. Isi hasil kegiatan dan selesaikan Agenda.',
                default => 'Periksa agenda '.$this->agenda->title.'.',
            },
            'url' => route('sales-agendas.edit', $this->agenda),
            'agenda_id' => $this->agenda->getKey(),
        ];
    }
}
