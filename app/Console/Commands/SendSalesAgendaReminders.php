<?php

namespace App\Console\Commands;

use App\Models\SalesAgenda;
use App\Notifications\SalesAgendaReminderNotification;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sales:send-agenda-reminders')]
#[Description('Kirim pengingat in-app untuk Agenda Sales yang terjadwal.')]
class SendSalesAgendaReminders extends Command
{
    public function handle(): int
    {
        $now = BusinessTime::now();
        $sent = 0;

        SalesAgenda::query()
            ->where('status', 'planned')
            ->whereNotNull('planned_start_at')
            ->whereNotNull('planned_end_at')
            ->with('owner')
            ->orderBy('id')
            ->chunkById(200, function ($agendas) use ($now, &$sent): void {
                foreach ($agendas as $agenda) {
                    foreach ($this->dueReminders($agenda, $now) as $reminderType) {
                        if ($this->deliver($agenda, $reminderType)) {
                            $sent++;
                        }
                    }
                }
            });

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function dueReminders(SalesAgenda $agenda, CarbonImmutable $now): array
    {
        $start = CarbonImmutable::instance($agenda->planned_start_at)->setTimezone(config('app.business_timezone', 'Asia/Jakarta'));
        $end = CarbonImmutable::instance($agenda->planned_end_at)->setTimezone(config('app.business_timezone', 'Asia/Jakarta'));

        return collect([
            'upcoming' => $now->gte($start->subMinutes(15)) && $now->lt($start),
            'starting_now' => $now->gte($start) && $now->lt($start->addMinute()),
            'ending_soon' => $now->gte($end->subMinutes(15)) && $now->lt($end),
            'overdue' => $now->gte($end),
        ])->filter()->keys()->all();
    }

    private function deliver(SalesAgenda $agenda, string $reminderType): bool
    {
        $recipient = $agenda->owner;

        if ($recipient === null) {
            return false;
        }

        $occurrenceKey = $agenda->planned_start_at
            ->setTimezone(config('app.business_timezone', 'Asia/Jakarta'))
            ->format('Y-m-d H:i:s');
        $inserted = DB::table('sales_agenda_notification_deliveries')->insertOrIgnore([
            'sales_agenda_id' => $agenda->getKey(),
            'recipient_id' => $recipient->getKey(),
            'notification_type' => $reminderType,
            'occurrence_key' => $occurrenceKey,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted !== 1) {
            return false;
        }

        $recipient->notify(new SalesAgendaReminderNotification($agenda, $reminderType));

        return true;
    }
}
