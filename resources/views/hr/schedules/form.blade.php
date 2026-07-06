@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-calendar"></i> {{ $mode === 'edit' ? __('Edit Schedule') : __('Add Schedule') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('schedule.update', $schedule) : route('schedule.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Employee') }}</label>
                                @if($mode === 'edit')
                                    @php($selectedEmployee = old('employee_id', $schedule->employee_id ?? null))
                                    <select name="employee_id" class="form-control js-example-basic-single" required>
                                        <option value="">{{ __('Select Employee') }}</option>
                                        @foreach($employees as $employee)
                                            @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                            @php($departmentName = $employee->department?->name ?? 'No Department')
                                            <option value="{{ $employee->id }}" {{ (string) $selectedEmployee === (string) $employee->id ? 'selected' : '' }}>{{ $employeeName }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    @php($selectedEmployees = collect(old('employee_ids', []))->map(fn ($id) => (string) $id)->all())
                                    <select name="employee_ids[]" class="form-control js-example-basic-multiple" multiple required>
                                        @foreach($employees as $employee)
                                            @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                            @php($departmentName = $employee->department?->name ?? 'No Department')
                                            <option value="{{ $employee->id }}" {{ in_array((string) $employee->id, $selectedEmployees, true) ? 'selected' : '' }}>{{ $employeeName }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Branch') }}</label>
                                @php($selectedBranch = old('branch_id', $schedule->branch_id ?? null))
                                <select name="branch_id" class="form-control js-example-basic-single" required>
                                    <option value="">{{ __('Select Branch') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (string) $selectedBranch === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Week Number') }}</label>
                                <input type="number" id="week_number" name="week_number" class="form-control" value="{{ old('week_number', $schedule->week_number ?? $defaultWeekNumber) }}" min="1" max="53" required>
                            </div>

                            <div class="col-md-8 form-group mb-3">
                                <label>{{ __('Week Date Range') }}</label>
                                <input type="text" id="week_date_range" class="form-control" value="" readonly>
                            </div>

                            @foreach([
                                's_monday' => __('Monday'),
                                's_tuesday' => __('Tuesday'),
                                's_wednesday' => __('Wednesday'),
                                's_thursday' => __('Thursday'),
                                's_friday' => __('Friday'),
                                's_saturday' => __('Saturday'),
                                's_sunday' => __('Sunday'),
                            ] as $field => $label)
                                <div class="col-md-3 form-group mb-3">
                                    <label>{{ $label }}</label>
                                    <input type="time" name="{{ $field }}" class="form-control" value="{{ old($field, $schedule->{$field} ?? '') }}">
                                </div>
                            @endforeach

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Schedule Notes') }}</label>
                                <textarea name="schedule_notes" class="form-control" rows="3" maxlength="255">{{ old('schedule_notes', $schedule->schedule_notes ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Schedule') : __('Create Schedule') }}
                        </button>
                        <a href="{{ route('schedule.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
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
        if ($.fn.select2) {
            $('.js-example-basic-single').select2({ width: '100%' });
            $('.js-example-basic-multiple').select2({
                width: '100%',
                placeholder: @json(__('Select Employees'))
            });
        }

        var weekInput = document.getElementById('week_number');
        var rangeInput = document.getElementById('week_date_range');

        function formatDate(date) {
            return date.toLocaleDateString(undefined, { month: 'short', day: '2-digit', year: 'numeric' });
        }

        function isoWeekStart(year, week) {
            var simple = new Date(year, 0, 1 + (week - 1) * 7);
            var day = simple.getDay();
            var isoWeekStartDate = new Date(simple);

            if (day <= 4) {
                isoWeekStartDate.setDate(simple.getDate() - simple.getDay() + 1);
            } else {
                isoWeekStartDate.setDate(simple.getDate() + 8 - simple.getDay());
            }

            return isoWeekStartDate;
        }

        function updateWeekRange() {
            if (!weekInput || !rangeInput) {
                return;
            }

            var week = parseInt(weekInput.value, 10);
            if (!week || week < 1 || week > 53) {
                rangeInput.value = '';
                return;
            }

            var currentYear = new Date().getFullYear();
            var start = isoWeekStart(currentYear, week);
            var end = new Date(start);
            end.setDate(start.getDate() + 6);
            rangeInput.value = formatDate(start) + ' - ' + formatDate(end);
        }

        if (weekInput) {
            weekInput.addEventListener('input', updateWeekRange);
            updateWeekRange();
        }
    })();
</script>
@endpush
