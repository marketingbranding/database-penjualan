<?php

namespace App\Services;

use App\Models\SalesDailyPlan;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DailyAgendaPlanner
{
    public const MORNING_START = '09:00';

    public const LUNCH_START = '12:00';

    public const AFTERNOON_START = '13:00';

    public const WORKDAY_END = '17:00';

    /**
     * Recalculate the deterministic timeline for a daily plan.
     *
     * Legacy/imported Agendas are intentionally ignored when they have no
     * duration. Only the new daily-planning rows are scheduled here.
     */
    public function recalculate(SalesDailyPlan $plan): int
    {
        $agendas = $plan->agendas()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('duration_minutes')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $cursor = $this->startCursor($plan->plan_date->toDateString());
        $total = 0;

        foreach ($agendas as $index => $agenda) {
            $duration = (int) $agenda->duration_minutes;

            if ($duration < 1) {
                throw ValidationException::withMessages([
                    'duration_minutes' => 'Durasi agenda harus lebih dari 0 menit.',
                ]);
            }

            [$start, $end, $cursor] = $this->allocate($cursor, $duration);
            $total += $duration;

            $agenda->forceFill([
                'sort_order' => $index + 1,
                'planned_start_at' => $start->utc()->toDateTimeString(),
                'planned_end_at' => $end->utc()->toDateTimeString(),
            ])->saveQuietly();
        }

        return $total;
    }

    public function effectiveMinutes(SalesDailyPlan $plan): int
    {
        return (int) $plan->agendas()
            ->where('status', '!=', 'cancelled')
            ->sum('duration_minutes');
    }

    public function validateDraftTotal(SalesDailyPlan $plan): void
    {
        $total = $this->effectiveMinutes($plan);

        if ($total > SalesDailyPlan::REQUIRED_WORK_MINUTES) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'Total rencana kerja tidak boleh melebihi 420 menit.',
            ]);
        }
    }

    public function validateSubmission(SalesDailyPlan $plan): int
    {
        if (! $plan->isWeekday()) {
            throw ValidationException::withMessages([
                'plan_date' => 'Sabtu dan Minggu tidak memerlukan submit rencana kerja.',
            ]);
        }

        $total = $this->effectiveMinutes($plan);

        if ($total !== SalesDailyPlan::REQUIRED_WORK_MINUTES) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'Rencana kerja hanya dapat dikirim jika totalnya tepat 420 menit.',
            ]);
        }

        return $total;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function allocate(CarbonImmutable $cursor, int $duration): array
    {
        $start = $this->normalizeCursor($cursor);
        $remaining = $duration;
        $end = $start;
        $current = $start;

        while ($remaining > 0) {
            $current = $this->normalizeCursor($current);
            $windowEnd = $current->hour < 12
                ? $current->setTime(12, 0)
                : $current->setTime(17, 0);
            $available = $current->diffInMinutes($windowEnd);

            if ($available <= 0) {
                throw ValidationException::withMessages([
                    'duration_minutes' => 'Agenda melebihi jendela kerja 09:00–12:00 dan 13:00–17:00.',
                ]);
            }

            $minutes = min($remaining, $available);
            $end = $current->addMinutes($minutes);
            $remaining -= $minutes;
            $current = $remaining > 0
                ? $current->setTime(13, 0)
                : $end;
        }

        return [$start, $end, $current];
    }

    private function normalizeCursor(CarbonImmutable $cursor): CarbonImmutable
    {
        $local = $cursor->setTimezone(config('app.business_timezone', 'Asia/Jakarta'));

        if ($local->lt($local->setTime(9, 0))) {
            return $local->setTime(9, 0);
        }

        if ($local->gte($local->setTime(12, 0)) && $local->lt($local->setTime(13, 0))) {
            return $local->setTime(13, 0);
        }

        return $local;
    }

    private function startCursor(string $date): CarbonImmutable
    {
        return BusinessTime::startOfDay($date)->setTime(9, 0);
    }
}
