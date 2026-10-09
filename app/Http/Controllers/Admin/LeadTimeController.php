<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLeadTimeTargetRequest;
use App\Models\LeadTimeTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LeadTimeController extends Controller
{
    public function index(): View
    {
        return view('admin.lead-times.index', ['targets' => LeadTimeTarget::query()->orderBy('id')->get()]);
    }

    public function update(UpdateLeadTimeTargetRequest $request, LeadTimeTarget $leadTimeTarget): RedirectResponse
    {
        $leadTimeTarget->update($request->validated());

        return back()->with('status', 'lead-time-updated');
    }
}
