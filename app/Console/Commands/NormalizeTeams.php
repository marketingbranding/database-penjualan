<?php

namespace App\Console\Commands;

use App\Services\TeamNormalizationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('oasis:normalize-teams {--mapping= : Mapping JSON path (defaults to storage/app/audits/legacy-organization-mapping-2026-10-19.json)} {--apply : Apply the validated normalization plan} {--deactivate-legacy : Deactivate legacy Teams only after final coverage validation} {--report= : Output JSON report path} {--confirm= : Required in production: NORMALIZE-OASIS-NEXT-TEAMS}')]
#[Description('Dry-run or apply deterministic OASIS Next Coordinator Team normalization')]
class NormalizeTeams extends Command
{
    public function handle(TeamNormalizationService $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $deactivateLegacy = (bool) $this->option('deactivate-legacy');
        if ($apply && app()->environment('production') && $this->option('confirm') !== 'NORMALIZE-OASIS-NEXT-TEAMS') {
            $this->error('Production apply requires --confirm=NORMALIZE-OASIS-NEXT-TEAMS.');

            return self::FAILURE;
        }

        try {
            $mapping = $this->option('mapping') ?: 'storage/app/audits/legacy-organization-mapping-2026-10-19.json';
            $report = $normalizer->run((string) $mapping, $apply, $deactivateLegacy);
            $reportPath = $this->option('report') ?: 'storage/app/audits/team-normalization-'.now()->format('Y-m-d').'.json';
            $absoluteReportPath = $this->writeReport((string) $reportPath, $report);
            $report['report_path'] = $absoluteReportPath;

            $this->renderSummary($report);

            return $report['status'] === 'FAILED' || $report['preflight_errors'] !== [] ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $report */
    private function renderSummary(array $report): void
    {
        $this->info(sprintf('Team normalization %s (%s).', strtoupper((string) $report['mode']), $report['status']));
        $this->table(
            ['Metric', 'Value'],
            collect($report['counts'] ?? [])->map(fn ($value, string $key): array => [$key, is_scalar($value) ? (string) $value : json_encode($value)])->values()->all(),
        );
        if (array_key_exists('coordinator_owned_agendas_resolved', $report['counts'] ?? [])) {
            $this->line('Coordinator-owned Agenda resolved: '.(int) $report['counts']['coordinator_owned_agendas_resolved']);
        }

        if ($report['branches'] !== []) {
            $this->newLine();
            $this->line('Coordinator and Sales Team plan:');
            foreach ($report['coordinators'] as $coordinator) {
                $this->line(sprintf(
                    'Coordinator #%d: legacy Teams [%s] -> %s (%s), Sales [%s]',
                    $coordinator['coordinator_id'],
                    implode(',', $coordinator['previous_team_ids']) ?: '-',
                    $coordinator['final_team_code'],
                    $coordinator['action'],
                    implode(',', $coordinator['sales_ids']) ?: '-',
                ));
            }
            foreach ($report['sales'] as $sales) {
                $this->line(sprintf(
                    'Sales #%d: category=%s, legacy Teams [%s] -> %s',
                    $sales['user_id'],
                    $sales['category'],
                    implode(',', $sales['previous_team_ids']) ?: '-',
                    $sales['final_team_code'] ?? 'NEEDS_MANUAL_MAPPING',
                ));
            }
        }

        if ($report['preflight_errors'] !== []) {
            $this->newLine();
            $this->error('Preflight blocked normalization:');
            foreach ($report['preflight_errors'] as $error) {
                $this->line(sprintf('- %s: %s', $error['code'], $error['message']));
            }
        }

        if ($report['readiness_blockers'] !== []) {
            $this->newLine();
            $this->warn('Normalization is not cutover-ready:');
            foreach ($report['readiness_blockers'] as $blocker) {
                $this->line(sprintf('- %s: %s', $blocker['code'], $blocker['message']));
            }
        }

        $this->info('Report: '.($report['report_path'] ?? '-'));
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $path, array $report): string
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\]/', $path) === 1
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $absolutePath;
    }
}
