<?php

namespace App\Console\Commands;

use App\Services\LeadOwnershipCleanupService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('oasis:cleanup-lead-ownership {--cutover-at= : Cutover timestamp} {--timezone=Asia/Jakarta : Timezone for cutover timestamp} {--apply : Apply deterministic repairs and dispositions} {--confirm= : Required production confirmation, use CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP} {--report= : Output JSON report path}')]
#[Description('Audit or safely clean up invalid Lead ownership')]
class CleanupLeadOwnership extends Command
{
    public function handle(LeadOwnershipCleanupService $cleanup): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && app()->environment('production') && $this->option('confirm') !== 'CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP') {
            $this->error('Production apply requires --confirm=CLEANUP-OASIS-NEXT-LEAD-OWNERSHIP.');

            return self::FAILURE;
        }

        try {
            $timezone = (string) $this->option('timezone');
            $cutoverAt = $this->option('cutover-at')
                ? CarbonImmutable::parse((string) $this->option('cutover-at'), $timezone)
                : CarbonImmutable::now($timezone);
            $report = $cleanup->run($cutoverAt, $timezone, $apply);
            $path = $this->option('report') ?: 'storage/app/audits/lead-ownership-cleanup-'.$cutoverAt->format('Y-m-d').'.json';
            $absolutePath = $this->absolutePath((string) $path);
            $report['report_path'] = $absolutePath;
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $this->info(sprintf('Lead ownership cleanup %s completed.', strtoupper((string) $report['mode'])));
            $this->table(
                ['Metric', 'Value'],
                collect($report['counts'])->map(fn ($value, string $key): array => [$key, is_scalar($value) ? (string) $value : json_encode($value)])->values()->all(),
            );
            $this->info('Report: '.$absolutePath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\]/', $path) === 1
            ? $path
            : base_path($path);
    }
}
