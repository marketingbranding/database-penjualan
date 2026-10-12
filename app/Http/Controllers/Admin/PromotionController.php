<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Branch;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Promotion;
use App\Support\CodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $search = trim((string) request('search'));
        $status = request('status');

        $promotions = Promotion::query()
            ->with(['targets.branch', 'targets.project', 'targets.projectBlock'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('promo_id', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive', 'upcoming', 'ended'], true), fn ($query) => $query->withStatus($status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.promotions.index', compact('promotions', 'search', 'status'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.promotions.create', $this->formData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePromotionRequest $request, CodeGenerator $codeGenerator): RedirectResponse
    {
        $promotion = DB::transaction(function () use ($request, $codeGenerator): Promotion {
            $promotion = Promotion::query()->create($request->safe()->only([
                'name', 'start_date', 'end_date', 'description',
            ]) + [
                'promo_id' => $codeGenerator->placeholder(),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);
            $promotion->update(['promo_id' => $codeGenerator->promotion($promotion->getKey())]);
            $promotion->targets()->createMany($request->normalizedTargets());

            return $promotion;
        });

        return redirect()->route('admin.promotions.edit', $promotion)->with('status', 'promotion-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.edit', [
            'promotion' => $promotion->load('targets'),
            ...$this->formData(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        DB::transaction(function () use ($request, $promotion): void {
            $promotion->update($request->safe()->only([
                'name', 'start_date', 'end_date', 'description',
            ]) + ($request->has('is_active') ? ['is_active' => $request->boolean('is_active')] : []));
            $promotion->targets()->delete();
            if ($request->normalizedTargets() !== []) {
                $promotion->targets()->createMany($request->normalizedTargets());
            }
        });

        return back()->with('status', 'promotion-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        abort_unless(request()->user()->can('promotions.delete'), 403);

        if ($promotion->salesCases()->exists()) {
            return back()->withErrors(['promotion' => 'Promo yang sudah dipakai pada data konsumen tidak dapat dihapus.']);
        }

        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('status', 'promotion-deleted');
    }

    public function toggleActive(Promotion $promotion): RedirectResponse
    {
        abort_unless(request()->user()->can('promotions.update'), 403);

        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('status', 'promotion-toggled');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::query()->where('is_active', true)->orderBy('name')->get(),
            'blocks' => ProjectBlock::query()->with('project.branch')->orderBy('code')->get(),
        ];
    }
}
