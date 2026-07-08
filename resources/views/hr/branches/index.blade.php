@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-location-pin"></i> {{ __('Branches') }}</h1>
        @if(auth()->user()?->hasPermission('branch.create'))
            <a href="{{ route('branch.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Branch') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-5">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search name/code/address/description') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('branch.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Image') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Address') }}</th>
                                    <th>{{ __('History') }}</th>
                                    <th>{{ __('Schedules') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($branches as $branch)
                                    <tr>
                                        <td>
                                            @if($branch->branch_image_path)
                                                <img src="{{ asset($branch->branch_image_path) }}" alt="{{ $branch->branch_name }}" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $branch->branch_name }}
                                            @if($branch->branch_description)
                                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($branch->branch_description, 80) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $branch->branch_code }}</td>
                                        <td>{{ $branch->branch_address }}</td>
                                        <td>{{ $branch->branch_histories_count }}</td>
                                        <td>{{ $branch->schedules_count }}</td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('branch.update'))
                                                <a href="{{ route('branch.edit', $branch) }}" title="{{ __('Edit Branch') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('branch.delete'))
                                                <form method="POST" action="{{ route('branch.destroy', $branch) }}" onsubmit="return confirm('Delete this branch?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete Branch') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No branches found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $branches->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
