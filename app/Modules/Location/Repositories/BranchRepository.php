<?php

namespace App\Modules\Location\Repositories;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BranchRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return Branch::query()
            ->withCount(['branchHistories', 'schedules'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('branch_name', 'like', "%{$q}%")
                        ->orWhere('branch_code', 'like', "%{$q}%")
                        ->orWhere('branch_address', 'like', "%{$q}%")
                        ->orWhere('branch_description', 'like', "%{$q}%");
                });
            })
            ->orderBy('branch_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Branch
    {
        return Branch::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Branch $branch, array $attributes): void
    {
        $branch->update($attributes);
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }
}
