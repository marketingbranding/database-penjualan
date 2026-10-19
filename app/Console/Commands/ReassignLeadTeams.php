<?php

namespace App\Console\Commands;

use App\Services\LeadTeamReassignmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('oasis:reassign-lead-teams {--mapping= : Authoritative organization mapping JSON path (defaults to storage/app/audits/legacy-organization-mapping-2026-10-19.json)} {--apply : Apply only SAFE_REASSIGN Lead team_id changes} {--report= : Output JSON report path} {--confirm= : Required in production: REASSIGN-OASIS-NEXT-LEAD-TEAMS}')]
#[Description('Dry-run or apply deterministic Lead Team reassignment from sales_pic_id')]
class ReassignLeadTeams extends Command
{
    public function handle(LeadTeamReassignmentService $reassignment): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && app()->environment('production') && $this->option('confirm') !== 'REASSIGN-OASIS-NEXT-LEAD-TEAMS') {
            $this->error('Production apply requires --confirm=REASSIGN-OASIS-NEXT-LEAD-TEAMS.');

            return self::FAILURE;
        }

        try {
            $mappingPath = $this->option('mapping') ?: 'storage/app/audits/legacy-organization-mapping-2026-10-19.json';
            $report = $reassignment->run((string) $mappingPath, $apply);
            $reportPath = $this->option('report') ?: 'storage/app/audits/lead-team-reassignment-'.now()->format('Y-m-d').'.json';
            $report['report_path'] = $this->absolutePath((string) $reportPath);
            $this->writeReport((string) $reportPath, $report);

            $this->renderSummary($report);

            return in_array($report['status'], ['FAILED', 'BLOCKED'], true) ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $report */
    private function renderSummary(array $report): void
    {
        $this->info(sprintf('Lead Team reassignment %s (%s).', strtoupper((string) $report['mode']), $report['status']));
        $this->table(
            ['Metric', 'Value'],
            collect($report['counts'] ?? [])->map(fn ($value, string $key): array => [$key, is_scalar($value) ? (string) $value : json_encode($value)])->values()->all(),
        );
        $this->line(sprintf(
            'Summary: leads_inspected=%d existing_consistent=%d safe_reassign=%d needs_review=%d changed=%d',
            $report['counts']['leads_inspected'],
            $report['counts']['existing_consistent'],
            $report['counts']['safe_reassign'],
            $report['counts']['needs_review'],
            $report['counts']['changed'],
        ));

        if ($report['reason_breakdown'] !== []) {
            $this->newLine();
            $this->line('Needs-review reasons:');
            foreach ($report['reason_breakdown'] as $reason => $count) {
                $this->line(sprintf('- %s=%d', $reason, $count));
            }
        }

        if ($report['preflight_errors'] !== []) {
            $this->newLine();
            $this->error('Preflight blocked reassignment:');
            foreach ($report['preflight_errors'] as $error) {
                $this->line(sprintf('- %s: %s', $error['code'], $error['message']));
            }
        }

        $this->info('Report: '.($report['report_path'] ?? '-'));
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $path, array $report): void
    {
        $absolutePath = $this->absolutePath($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\]/', $path) === 1
            ? $path
            : base_path($path);
    }
}
