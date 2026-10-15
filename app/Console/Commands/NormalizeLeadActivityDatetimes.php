<?php

namespace App\Console\Commands;

use App\Models\LeadActivity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('datetime:normalize-lead-activities {--force : Apply the conversion instead of showing a dry run} {--reverse= : Reverse a previously applied batch ID}')]
#[Description('Classify and normalize legacy LeadActivity datetime values to UTC')]
class NormalizeLeadActivityDatetimes extends Command
{
    public function handle(): int
    {
        if ($this->option('reverse') !== null) {
            return $this->reverse((string) $this->option('reverse'));
        }

        $counts = ['system' => 0, 'user' => 0, 'already_classified' => 0];
        $samples = ['system' => [], 'user' => []];
        $batchId = (string) Str::uuid();

        $classify = function () use (&$counts, &$samples, $batchId): void {
            LeadActivity::query()->orderBy('id')->chunkById(500, function ($activities) use (&$counts, &$samples, $batchId): void {
                foreach ($activities as $activity) {
                    if ($activity->happened_at_source !== null) {
                        $counts['already_classified']++;

                        continue;
                    }

                    $classification = $this->classification($activity);
                    $counts[$classification]++;
                    if (count($samples[$classification]) < 10) {
                        $samples[$classification][] = sprintf('#%d happened_at=%s created_at=%s', $activity->id, $activity->happened_at, $activity->created_at);
                    }

                    if ($this->option('force')) {
                        $this->applyActivityChange($activity, $classification, $batchId);
                    }
                }
            });
        };

        if ($this->option('force')) {
            DB::transaction($classify);
        } else {
            $classify();
        }

        $this->line('System-written: '.$counts['system']);
        $this->line('User-entered: '.$counts['user']);
        $this->line('Already classified/skipped: '.$counts['already_classified']);
        $this->line('Timezone bisnis: '.config('app.business_timezone', 'Asia/Jakarta'));
        foreach ($samples as $classification => $rows) {
            $this->line('Sampel '.$classification.':');
            foreach ($rows as $row) {
                $this->line('  '.$row);
            }
        }

        if (! $this->option('force')) {
            $this->info('Dry-run selesai. Gunakan --force setelah backup production untuk menerapkan perubahan.');

            return self::SUCCESS;
        }

        $this->info('Batch diterapkan: '.$batchId);
        logger()->info('Lead activity datetime normalization applied', ['batch_id' => $batchId, 'counts' => $counts]);

        return self::SUCCESS;
    }

    private function classification(LeadActivity $activity): string
    {
        $happenedAt = CarbonImmutable::parse($activity->getRawOriginal('happened_at'), 'UTC');
        $createdAt = CarbonImmutable::parse($activity->getRawOriginal('created_at'), 'UTC');

        return abs($happenedAt->diffInSeconds($createdAt, false)) <= 120 ? 'system' : 'user';
    }

    private function applyActivityChange(LeadActivity $activity, string $classification, string $batchId): void
    {
        $oldValue = (string) $activity->getRawOriginal('happened_at');
        $newValue = $classification === 'user'
            ? CarbonImmutable::parse($oldValue, config('app.business_timezone', 'Asia/Jakarta'))->utc()->format('Y-m-d H:i:s')
            : $oldValue;

        DB::table('lead_activities')->where('id', $activity->id)->update([
            'happened_at' => $newValue,
            'happened_at_source' => $classification,
        ]);
        DB::table('datetime_normalization_changes')->insert([
            'batch_id' => $batchId,
            'table_name' => 'lead_activities',
            'column_name' => 'happened_at',
            'row_id' => $activity->id,
            'classification' => $classification,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'old_source' => null,
            'new_source' => $classification,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reverse(string $batchId): int
    {
        if (! $this->option('force')) {
            $this->error('Gunakan --force bersama --reverse untuk membalikkan batch.');

            return self::FAILURE;
        }

        $count = 0;
        DB::transaction(function () use ($batchId, &$count): void {
            $changes = DB::table('datetime_normalization_changes')
                ->where('batch_id', $batchId)
                ->whereNull('reversed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($changes as $change) {
                DB::table($change->table_name)->where('id', $change->row_id)->update([
                    $change->column_name => $change->old_value,
                    'happened_at_source' => $change->old_source,
                ]);
                DB::table('datetime_normalization_changes')->where('id', $change->id)->update(['reversed_at' => now(), 'updated_at' => now()]);
                $count++;
            }
        });

        logger()->info('Lead activity datetime normalization reversed', ['batch_id' => $batchId, 'count' => $count]);
        $this->info('Baris dibalikkan: '.$count);

        return self::SUCCESS;
    }
}
