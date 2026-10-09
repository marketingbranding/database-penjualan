<?php

namespace App\Observers;

use App\Models\Lot;
use App\Models\LotStatusHistory;
use Illuminate\Support\Facades\Auth;

class LotObserver
{
    /**
     * Handle the Lot "created" event.
     */
    public function created(Lot $lot): void
    {
        //
    }

    /**
     * Handle the Lot "updated" event.
     */
    public function updated(Lot $lot): void
    {
        if (! $lot->wasChanged('status')) {
            return;
        }

        LotStatusHistory::query()->create([
            'lot_id' => $lot->getKey(),
            'user_id' => Auth::id(),
            'from_status' => $lot->getRawOriginal('status'),
            'to_status' => $lot->status->value,
            'source' => $lot->statusChangeSource,
            'reason' => $lot->statusChangeReason,
        ]);
    }

    /**
     * Handle the Lot "deleted" event.
     */
    public function deleted(Lot $lot): void
    {
        //
    }

    /**
     * Handle the Lot "restored" event.
     */
    public function restored(Lot $lot): void
    {
        //
    }

    /**
     * Handle the Lot "force deleted" event.
     */
    public function forceDeleted(Lot $lot): void
    {
        //
    }
}
