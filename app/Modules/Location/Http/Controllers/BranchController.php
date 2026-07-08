<?php

namespace App\Modules\Location\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\Location\Http\Requests\StoreBranchRequest;
use App\Modules\Location\Http\Requests\UpdateBranchRequest;
use App\Modules\Location\Repositories\BranchRepository;
use App\Modules\Location\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchRepository $branchRepository,
        private readonly BranchService $branchService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.branches.index', [
            'branches' => $this->branchRepository->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.branches.form', [
            'mode' => 'create',
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->branchService->createBranch($request->validated());

        return redirect()->route('branch.index')->with('success', __('Branch created successfully.'));
    }

    public function edit(Branch $branch): View
    {
        return view('hr.branches.form', [
            'mode' => 'edit',
            'branch' => $branch,
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->branchService->updateBranch($branch, $request->validated());

        return redirect()->route('branch.index')->with('success', __('Branch updated successfully.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->branchService->deleteBranch($branch);

        return redirect()->route('branch.index')->with('success', __('Branch deleted successfully.'));
    }

    public function branchHistory()
    {
    }
}
