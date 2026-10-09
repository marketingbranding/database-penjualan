<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Str;

final class CodeGenerator
{
    public function placeholder(): string
    {
        return 'AUTO-'.Str::lower(Str::random(20));
    }

    public function promotion(int $id): string
    {
        return 'PROMO-'.today()->format('Ym').'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public function branch(int $id): string
    {
        return 'CAB-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    public function project(int $id): string
    {
        return 'PRJ-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    public function team(int $id): string
    {
        return 'TEAM-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    /** @return array{code: string, sort_order: int} */
    public function nextBlock(Project $project): array
    {
        $sortOrder = ((int) $project->blocks()->max('sort_order')) + 1;

        do {
            $code = $this->letters($sortOrder);
            $sortOrder++;
        } while ($project->blocks()->where('code', $code)->exists());

        return [
            'code' => $code,
            'sort_order' => $sortOrder - 1,
        ];
    }

    private function letters(int $number): string
    {
        $letters = '';

        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }
}
