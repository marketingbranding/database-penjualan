<?php

namespace App\Notifications;

use App\LotStatus;
use App\Models\Lot;
use Illuminate\Notifications\Notification;

class LotStatusChangedNotification extends Notification
{
    public function __construct(
        public Lot $lot,
        public LotStatus $previousStatus,
        public LotStatus $newStatus,
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
            'type' => 'lot.status-changed',
            'title' => 'Status kavling berubah',
            'message' => $this->lot->project->name.'-'.$this->lot->code.' berubah dari '.$this->previousStatus->label().' menjadi '.$this->newStatus->label().'.',
            'url' => route('admin.lots.index', [
                'branch_id' => $this->lot->project->branch_id,
                'project_id' => $this->lot->project_id,
                'block_id' => $this->lot->project_block_id,
            ]),
        ];
    }
}
