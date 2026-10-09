<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Support\CodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $branches = Branch::query()->withCount('projects')->orderBy('name')->paginate(20);

        return view('admin.branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.branches.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBranchRequest $request, CodeGenerator $codeGenerator): RedirectResponse
    {
        $branch = DB::transaction(function () use ($request, $codeGenerator): Branch {
            $branch = Branch::query()->create($request->validated() + ['code' => $codeGenerator->placeholder()]);
            $branch->update(['code' => $codeGenerator->branch($branch->getKey())]);

            return $branch;
        });

        return redirect()->route('admin.branches.edit', $branch)->with('status', 'branch-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Branch $branch): View
    {
        return view('admin.branches.edit', compact('branch'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->safe()->only(['name', 'is_active']));

        return back()->with('status', 'branch-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        abort_unless(request()->user()->can('branches.delete'), 403);

        if ($branch->projects()->exists() || $branch->users()->exists()) {
            return back()->withErrors(['branch' => 'Cabang yang memiliki proyek atau admin tidak dapat dihapus.']);
        }

        $branch->delete();

        return redirect()->route('admin.branches.index')->with('status', 'branch-deleted');
    }
}
