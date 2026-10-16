<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

class LeadCreatedForSalesNotification extends Notification
{
    public function __construct(public Lead $lead, public string $creatorName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead.created_for_sales',
            'title' => 'Lead baru untuk Anda',
            'message' => 'Lead "'.$this->lead->nama_konsumen.'" dibuat oleh '.$this->creatorName.'.',
            'url' => route('leads.show', $this->lead),
        ];
    }
}
