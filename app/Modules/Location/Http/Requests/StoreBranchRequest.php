<?php

namespace App\Modules\Location\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:50', Rule::unique(Branch::class, 'branch_code')],
            'branch_address' => ['required', 'string', 'max:255'],
            'branch_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'branch_description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
