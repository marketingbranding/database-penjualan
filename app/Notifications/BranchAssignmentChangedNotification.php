<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Notifications\Notification;

class BranchAssignmentChangedNotification extends Notification
{
    public function __construct(public ?Branch $branch) {}

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
            'type' => 'user.branch-changed',
            'title' => 'Penugasan cabang berubah',
            'message' => $this->branch
                ? 'Anda ditugaskan ke cabang '.$this->branch->name.'.'
                : 'Penugasan cabang Anda telah dilepas.',
            'url' => route('profile.edit'),
        ];
    }
}
