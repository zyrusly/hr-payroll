<?php

namespace App\Modules\Location\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
        /** @var Branch $branch */
        $branch = $this->route('branch');

        return [
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Branch::class, 'branch_code')->ignore($branch->id),
            ],
            'branch_address' => ['required', 'string', 'max:255'],
            'branch_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_branch_image' => ['nullable', 'boolean'],
            'branch_description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
