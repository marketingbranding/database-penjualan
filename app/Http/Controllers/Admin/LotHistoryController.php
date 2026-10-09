<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use Illuminate\Contracts\View\View;

class LotHistoryController extends Controller
{
    public function __invoke(Lot $lot): View
    {
        $lot->load(['project.branch', 'block', 'statusHistories.user']);
        $user = request()->user();

        abort_unless(
            $user->can('lots.view')
                && ($user->canAccessAllBranches() || $user->branch_id === $lot->project->branch_id),
            403,
        );

        return view('admin.lots.history', compact('lot'));
    }
}
