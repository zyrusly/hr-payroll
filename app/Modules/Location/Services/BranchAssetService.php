<?php

namespace App\Modules\Location\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BranchAssetService
{
    public function storeImage(?UploadedFile $file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return null;
        }

        $uploadDir = public_path('assets/uploads/branches');
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $filename = 'branch_'.time().'_'.Str::random(8).'.'.$extension;
        $file->move($uploadDir, $filename);

        return 'assets/uploads/branches/'.$filename;
    }

    public function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'assets/uploads/branches/')) {
            File::delete(public_path($path));
        }
    }
}
