<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SalesLeadTarget;
use App\Models\User;
use Carbon\CarbonImmutable;

class SalesLeadTargetService
{
    /**
     * Target progress counts every operational Lead assigned through
     * sales_pic_id, regardless of creator or current pipeline status.
     * Lead's global operational_cutover scope excludes only approved
     * EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED dispositions.
     */
    public function progress(User $sales, CarbonImmutable|string|null $period = null): array
    {
        $date = $period instanceof CarbonImmutable
            ? $period
            : CarbonImmutable::parse($period ?: now(), config('app.business_timezone', 'Asia/Jakarta'));
        $target = SalesLeadTarget::query()
            ->where('sales_id', $sales->getKey())
            ->where('period_year', $date->year)
            ->where('period_month', $date->month)
            ->first();
        $achieved = Lead::query()
            ->where('sales_pic_id', $sales->getKey())
            ->whereBetween('tanggal_lead', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])
            ->count();
        $goal = (int) ($target?->lead_target ?? 0);

        return [
            'target' => $goal,
            'achieved' => $achieved,
            'percentage' => $goal > 0 ? min(100, (int) round(($achieved / $goal) * 100)) : 0,
            'period' => $date,
        ];
    }

    public function forSales(User $sales, CarbonImmutable|string|null $period = null): ?SalesLeadTarget
    {
        $date = $period instanceof CarbonImmutable
            ? $period
            : CarbonImmutable::parse($period ?: now(), config('app.business_timezone', 'Asia/Jakarta'));

        return SalesLeadTarget::query()
            ->where('sales_id', $sales->getKey())
            ->where('period_year', $date->year)
            ->where('period_month', $date->month)
            ->first();
    }
}
