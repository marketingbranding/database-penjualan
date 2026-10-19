<?php

namespace App\Console\Commands;

use App\Services\OrganizationAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('oasis:audit-organization {--json : Print the complete audit as JSON} {--report= : Write the complete audit JSON to this path}')]
#[Description('Run a read-only organization and operational ownership audit')]
class AuditOrganization extends Command
{
    public function handle(OrganizationAuditService $audit): int
    {
        try {
            $report = $audit->run();
            $reportPath = $this->option('report');

            if ($reportPath !== null) {
                $reportPath = $this->writeReport((string) $reportPath, $report);
                $report['report_path'] = $reportPath;
            }

            if ($this->option('json')) {
                $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $this->renderSummary($report);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $report */
    private function renderSummary(array $report): void
    {
        $this->info('Read-only organization audit completed. No business records were modified.');
        $this->table(
            ['Metric', 'Value'],
            collect($report['counts'])->map(fn (int $value, string $key): array => [$key, number_format($value)])->values()->all(),
        );

        $this->newLine();
        $this->line('Issues:');
        foreach ($report['issues'] as $issue) {
            $this->line(sprintf('[%s] %s (%s): %s', $issue['severity'], $issue['code'], number_format($issue['count']), $issue['message']));
        }

        $this->newLine();
        $this->line('Coordinator mappings:');
        $this->table(
            ['ID', 'Name', 'Branch', 'Status', 'Active teams', 'Owned teams'],
            collect($report['coordinators'])->map(fn (array $coordinator): array => [
                $coordinator['id'], $coordinator['name'], $coordinator['branch_name'] ?? '-', $coordinator['mapping_status'],
                count($coordinator['active_team_ids']), count($coordinator['coordinated_team_ids']),
            ])->all(),
        );

        $this->newLine();
        $this->line('Sales mappings:');
        $this->table(
            ['ID', 'Name', 'Branch', 'Status', 'Active teams', 'Expected coordinator'],
            collect($report['sales'])->map(fn (array $sale): array => [
                $sale['id'], $sale['name'], $sale['branch_name'] ?? '-', $sale['mapping_status'], $sale['active_team_count'],
                collect($sale['expected_coordinators'])->pluck('name')->join(', ') ?: '-',
            ])->all(),
        );

        if (isset($report['report_path'])) {
            $this->info('Report: '.$report['report_path']);
        }
    }

    /** @param array<string, mixed> $report */
    private function writeReport(string $path, array $report): string
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\]/', $path)
            ? $path
            : base_path($path);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $absolutePath;
    }
}
