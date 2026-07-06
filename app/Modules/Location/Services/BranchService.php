<?php

namespace App\Modules\Location\Services;

use App\Models\Branch;
use App\Modules\Location\Repositories\BranchRepository;
use Illuminate\Support\Facades\DB;

class BranchService
{
    public function __construct(
        private readonly BranchRepository $branchRepository,
        private readonly BranchAssetService $branchAssetService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createBranch(array $payload): Branch
    {
        return DB::transaction(function () use ($payload): Branch {
            $imagePath = $this->branchAssetService->storeImage($payload['branch_image'] ?? null);

            return $this->branchRepository->create([
                'branch_name' => $payload['branch_name'],
                'branch_code' => $payload['branch_code'],
                'branch_address' => $payload['branch_address'],
                'branch_image_path' => $imagePath,
                'branch_description' => $payload['branch_description'] ?? null,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateBranch(Branch $branch, array $payload): Branch
    {
        return DB::transaction(function () use ($branch, $payload): Branch {
            $oldImagePath = $branch->branch_image_path;
            $newImagePath = $this->branchAssetService->storeImage($payload['branch_image'] ?? null);
            $removeImage = (bool) ($payload['remove_branch_image'] ?? false);

            $attributes = [
                'branch_name' => $payload['branch_name'],
                'branch_code' => $payload['branch_code'],
                'branch_address' => $payload['branch_address'],
                'branch_description' => $payload['branch_description'] ?? null,
            ];

            if ($newImagePath !== null) {
                $attributes['branch_image_path'] = $newImagePath;
            } elseif ($removeImage) {
                $attributes['branch_image_path'] = null;
            }

            $this->branchRepository->update($branch, $attributes);

            if (($newImagePath !== null || $removeImage) && $oldImagePath !== $newImagePath) {
                $this->branchAssetService->deleteImage($oldImagePath);
            }

            return $branch->fresh() ?? $branch;
        });
    }

    public function deleteBranch(Branch $branch): void
    {
        DB::transaction(function () use ($branch): void {
            $imagePath = $branch->branch_image_path;

            $this->branchRepository->delete($branch);
            $this->branchAssetService->deleteImage($imagePath);
        });
    }
}
