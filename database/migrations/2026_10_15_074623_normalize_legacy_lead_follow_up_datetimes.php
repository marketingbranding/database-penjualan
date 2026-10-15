<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $count = 0;

        DB::table('leads')->whereNotNull('next_follow_up_at')->orderBy('id')->chunkById(500, function ($leads) use (&$count): void {
            foreach ($leads as $lead) {
                $oldValue = (string) $lead->next_follow_up_at;
                $newValue = CarbonImmutable::parse($oldValue, config('app.business_timezone', 'Asia/Jakarta'))->utc()->format('Y-m-d H:i:s');

                DB::table('leads')->where('id', $lead->id)->update(['next_follow_up_at' => $newValue]);
                DB::table('datetime_normalization_changes')->insert([
                    'batch_id' => 'phase-4-5-next-follow-up',
                    'table_name' => 'leads',
                    'column_name' => 'next_follow_up_at',
                    'row_id' => $lead->id,
                    'classification' => 'legacy_user',
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'old_source' => null,
                    'new_source' => 'user',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }
        });

        logger()->info('Legacy lead follow-up datetimes normalized', ['count' => $count]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $count = 0;
        DB::transaction(function () use (&$count): void {
            $changes = DB::table('datetime_normalization_changes')
                ->where('batch_id', 'phase-4-5-next-follow-up')
                ->where('table_name', 'leads')
                ->where('column_name', 'next_follow_up_at')
                ->whereNull('reversed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($changes as $change) {
                DB::table('leads')->where('id', $change->row_id)->update(['next_follow_up_at' => $change->old_value]);
                DB::table('datetime_normalization_changes')->where('id', $change->id)->update(['reversed_at' => now(), 'updated_at' => now()]);
                $count++;
            }
        });

        logger()->info('Legacy lead follow-up datetime normalization reversed', ['count' => $count]);
    }
};
