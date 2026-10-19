<?php

namespace App\Console\Commands;

use App\Services\OasisCrmMigrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('migration:import-oasis {--source=legacy_oasis : Source database connection name} {--apply : Apply the migration instead of running a dry run} {--delta : Import only new or changed source rows} {--since= : Previous successful sync timestamp} {--freeze-at= : Source cutoff timestamp} {--timezone=Asia/Jakarta : Timezone for --since and --freeze-at} {--confirm= : Required production confirmation, use IMPORT-OASIS-CRM} {--report= : JSON report path} {--copy-evidence : Copy agenda evidence files when applying}')]
#[Description('Audit or migrate Buku Saku data from the legacy Oasis CRM')]
class ImportLegacyOasis extends Command
{
    public function handle(OasisCrmMigrationService $migration): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && app()->environment('production') && $this->option('confirm') !== 'IMPORT-OASIS-CRM') {
            $this->error('Production apply requires --confirm=IMPORT-OASIS-CRM.');

            return self::FAILURE;
        }

        try {
            $report = $migration->run(
                sourceConnection: (string) $this->option('source'),
                apply: $apply,
                copyEvidence: (bool) $this->option('copy-evidence'),
                output: $this->output,
                reportPath: $this->option('report') !== null ? (string) $this->option('report') : null,
                delta: (bool) $this->option('delta'),
                since: $this->option('since') !== null ? (string) $this->option('since') : null,
                freezeAt: $this->option('freeze-at') !== null ? (string) $this->option('freeze-at') : null,
                timezone: (string) $this->option('timezone'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf('Mode: %s', $report['mode']));

        foreach ($report['counts'] as $key => $count) {
            $this->line(sprintf('%-34s %s', $key, number_format($count)));
        }

        if ($report['report_path'] !== null) {
            $this->newLine();
            $this->info('Report: '.$report['report_path']);
        }

        return $report['has_blocking_issues'] ? self::FAILURE : self::SUCCESS;
    }
}
