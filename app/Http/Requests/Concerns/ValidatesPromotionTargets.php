<?php

namespace App\Http\Requests\Concerns;

use App\Models\Branch;
use App\Models\Project;
use App\Models\ProjectBlock;
use Illuminate\Validation\Validator;

trait ValidatesPromotionTargets
{
    /** @return array<int, array<string, int|null>> */
    public function normalizedTargets(): array
    {
        return collect($this->input('targets', []))
            ->map(function (array $target): array {
                $id = (int) ($target['target_id'] ?? 0);

                return match ($target['type'] ?? null) {
                    'project' => ['branch_id' => null, 'project_id' => $id, 'project_block_id' => null],
                    'block' => ['branch_id' => null, 'project_id' => null, 'project_block_id' => $id],
                    default => ['branch_id' => $id, 'project_id' => null, 'project_block_id' => null],
                };
            })
            ->values()
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('targets', []) as $index => $target) {
                $type = $target['type'] ?? null;
                $id = (int) ($target['target_id'] ?? 0);

                $valid = match ($type) {
                    'branch' => Branch::query()->where('is_active', true)->whereKey($id)->exists(),
                    'project' => Project::query()->where('is_active', true)->whereKey($id)->exists(),
                    'block' => ProjectBlock::query()->whereKey($id)->exists(),
                    default => false,
                };

                if (! $valid) {
                    $validator->errors()->add("targets.$index.target_id", 'Target promo tidak valid.');
                }
            }
        });
    }
}
