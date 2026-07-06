@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-calendar"></i> {{ __('Schedules') }}</h1>
        @if(auth()->user()?->hasPermission('schedule.create'))
            <a href="{{ route('schedule.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Schedule') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search code/employee/branch') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="employee_id" class="form-control js-example-basic-single">
                                <option value="">{{ __('All Employees') }}</option>
                                @foreach($employees as $employee)
                                    @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    <option value="{{ $employee->id }}" {{ (string) $filters['employee_id'] === (string) $employee->id ? 'selected' : '' }}>{{ $employeeName }} ({{ $employee->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="branch_id" class="form-control js-example-basic-single">
                                <option value="">{{ __('All Branches') }}</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) $filters['branch_id'] === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1">
                            <input type="number" name="week_number" value="{{ $filters['week_number'] }}" class="form-control" min="1" max="53" placeholder="{{ __('Week') }}">
                        </div>
                        <div class="col-md-1">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('schedule.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Branch') }}</th>
                                    <th>{{ __('Week') }}</th>
                                    <th>{{ __('Mon') }}</th>
                                    <th>{{ __('Tue') }}</th>
                                    <th>{{ __('Wed') }}</th>
                                    <th>{{ __('Thu') }}</th>
                                    <th>{{ __('Fri') }}</th>
                                    <th>{{ __('Sat') }}</th>
                                    <th>{{ __('Sun') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($schedules as $schedule)
                                    @php($weekStart = \Carbon\CarbonImmutable::now()->setISODate((int) now()->year, (int) $schedule->week_number))
                                    @php($weekEnd = $weekStart->addDays(6))
                                    <tr>
                                        <td>
                                            {{ $schedule->schedule_code }}
                                            @if($schedule->schedule_notes)
                                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($schedule->schedule_notes, 70) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ trim(($schedule->employee?->first_name ?? '').' '.($schedule->employee?->last_name ?? '')) ?: '-' }}
                                            <div class="small text-muted">{{ $schedule->employee?->employee_code ?? '-' }}</div>
                                        </td>
                                        <td>{{ $schedule->branch?->branch_name ?? '-' }}</td>
                                        <td>
                                            {{ $schedule->week_number }}
                                            <div class="small text-muted">{{ $weekStart->format('M d') }} - {{ $weekEnd->format('M d, Y') }}</div>
                                        </td>
                                        <td>{{ $schedule->s_monday ?: '-' }}</td>
                                        <td>{{ $schedule->s_tuesday ?: '-' }}</td>
                                        <td>{{ $schedule->s_wednesday ?: '-' }}</td>
                                        <td>{{ $schedule->s_thursday ?: '-' }}</td>
                                        <td>{{ $schedule->s_friday ?: '-' }}</td>
                                        <td>{{ $schedule->s_saturday ?: '-' }}</td>
                                        <td>{{ $schedule->s_sunday ?: '-' }}</td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('schedule.update'))
                                                <a href="{{ route('schedule.edit', $schedule) }}" title="{{ __('Edit Schedule') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('schedule.delete'))
                                                <form method="POST" action="{{ route('schedule.destroy', $schedule) }}" onsubmit="return confirm('Delete this schedule?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete Schedule') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center">{{ __('No schedules found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $schedules->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        if ($.fn.select2) {
            $('.js-example-basic-single').select2({ width: '100%' });
        }
    })();
</script>
@endpush
