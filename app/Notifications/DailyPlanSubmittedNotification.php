<?php

namespace App\Notifications;

use App\Models\SalesDailyPlan;
use Illuminate\Notifications\Notification;

class DailyPlanSubmittedNotification extends Notification
{
    public function __construct(public SalesDailyPlan $plan) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $count = $this->plan->agendas()->where('status', '!=', 'cancelled')->count();
        $minutes = (int) $this->plan->agendas()->where('status', '!=', 'cancelled')->sum('duration_minutes');

        return [
            'type' => 'daily-plan.submitted',
            'title' => 'Rencana kerja baru',
            'message' => $this->plan->sales->name.' mengirim Agenda kerja '.$this->plan->plan_date->translatedFormat('d F').' · '.$count.' agenda · '.$minutes.' menit.',
            'url' => route('daily-plans.show', $this->plan),
        ];
    }
}
