@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-location-pin"></i> {{ $mode === 'edit' ? __('Edit Branch') : __('Add Branch') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('branch.update', $branch) : route('branch.store') }}" enctype="multipart/form-data">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-12 form-group mb-4">
                                <label>{{ __('Branch Image') }}</label>
                                <div class="employee-avatar-card">
                                    <div class="employee-avatar-preview">
                                        @if(!empty($branch->branch_image_path ?? null))
                                            <img src="{{ asset($branch->branch_image_path) }}" alt="{{ __('Branch Image') }}">
                                        @else
                                            <i class="icon-location-pin employee-avatar-icon"></i>
                                        @endif
                                    </div>
                                    <div class="employee-avatar-actions">
                                        <input type="file" name="branch_image" id="branch_image" accept=".jpg,.jpeg,.png,.webp">
                                        <label for="branch_image" class="btn btn-custom btn-sm mb-2">
                                            <i class="icon-picture"></i> {{ __('Upload Image') }}
                                        </label>
                                        <small id="branch_image_file_name" class="text-muted d-block">{{ __('No file chosen') }}</small>
                                        <small class="text-muted d-block mt-1">{{ __('JPG, PNG, WEBP. Max 2MB.') }}</small>
                                        @if(!empty($branch->branch_image_path ?? null))
                                            <label class="employee-avatar-remove mt-2">
                                                <input type="checkbox" name="remove_branch_image" value="1">
                                                <span>{{ __('Remove current image') }}</span>
                                            </label>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Branch Name') }}</label>
                                <input type="text" name="branch_name" class="form-control" value="{{ old('branch_name', $branch->branch_name ?? '') }}" maxlength="255" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Branch Code') }}</label>
                                <input type="text" name="branch_code" class="form-control" value="{{ old('branch_code', $branch->branch_code ?? '') }}" maxlength="50" required>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Branch Address') }}</label>
                                <input type="text" name="branch_address" class="form-control" value="{{ old('branch_address', $branch->branch_address ?? '') }}" maxlength="255" required>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Description') }}</label>
                                <textarea name="branch_description" class="form-control" rows="3" maxlength="2000">{{ old('branch_description', $branch->branch_description ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Branch') : __('Create Branch') }}
                        </button>
                        <a href="{{ route('branch.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var branchImage = document.getElementById('branch_image');
        var branchImageFileName = document.getElementById('branch_image_file_name');

        if (!branchImage || !branchImageFileName) {
            return;
        }

        branchImage.addEventListener('change', function () {
            branchImageFileName.textContent = branchImage.files.length ? branchImage.files[0].name : @json(__('No file chosen'));
        });
    })();
</script>
@endpush
