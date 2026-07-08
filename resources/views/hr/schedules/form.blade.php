@extends('layouts.backend')

@push('styles')
<style>
    .copy-previous-week-label {
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .schedule-day-row {
        background: #f8f9fa;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
    }

    .schedule-day-name {
        font-weight: 600;
    }
</style>
@endpush

@section('content')
@php
    $dayFields = [
        'monday' => __('Monday'),
        'tuesday' => __('Tuesday'),
        'wednesday' => __('Wednesday'),
        'thursday' => __('Thursday'),
        'friday' => __('Friday'),
        'saturday' => __('Saturday'),
        'sunday' => __('Sunday'),
    ];
    $dayStatuses = [
        'working' => __('Working'),
        'rest_day' => __('Rest Day'),
        'day_off' => __('Day Off'),
        'leave' => __('Leave'),
        'holiday' => __('Holiday'),
    ];
    $formatTimeInput = static fn ($time): string => $time ? substr((string) $time, 0, 5) : '';
    $scheduleDaysByName = $mode === 'edit' ? $schedule->days->keyBy('day_of_week') : collect();
@endphp
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
                                <label>{{ __('Default Branch') }}</label>
                                @php($selectedDefaultBranch = old('default_branch_id', $schedule->default_branch_id ?? $schedule->branch_id ?? null))
                                <select name="default_branch_id" class="form-control js-example-basic-single" required data-default-branch>
                                    <option value="">{{ __('Select Branch') }}</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (string) $selectedDefaultBranch === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Default Start Time') }}</label>
                                <input type="time" name="default_start_time" class="form-control" value="{{ old('default_start_time', $formatTimeInput($schedule->default_start_time ?? '09:00')) }}" required data-default-start>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Default End Time') }}</label>
                                <input type="time" name="default_end_time" class="form-control" value="{{ old('default_end_time', $formatTimeInput($schedule->default_end_time ?? '18:00')) }}" required data-default-end>
                            </div>

                            @if($mode === 'edit')
                                <div class="col-md-4 form-group mb-3">
                                    <label>{{ __('Year') }}</label>
                                    <input type="number" name="year" class="form-control schedule-year" value="{{ old('year', $schedule->year ?? $defaultYear) }}" min="2000" max="2100" required>
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label>{{ __('Week Number') }}</label>
                                    <input type="number" name="week_number" class="form-control schedule-week-number" value="{{ old('week_number', $schedule->week_number ?? $defaultWeekNumber) }}" min="1" max="53" required>
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label>{{ __('Week Date Range') }}</label>
                                    <input type="text" class="form-control schedule-week-range" value="" readonly>
                                </div>

                                <div class="col-md-12 mb-2">
                                    <h5 class="mb-0">{{ __('Daily Schedule') }}</h5>
                                    <div class="small text-muted">{{ __('Leave branch or time blank to use the weekly defaults.') }}</div>
                                </div>

                                @foreach($dayFields as $dayKey => $label)
                                    @php($day = $scheduleDaysByName->get($dayKey))
                                    @php($selectedStatus = old("days.$dayKey.status", $day->status ?? ($loop->index < 5 ? 'working' : 'rest_day')))
                                    @php($selectedBranch = old("days.$dayKey.branch_id", $day->branch_id ?? ''))
                                    <div class="col-md-12 schedule-day-row" data-day-row>
                                        <div class="row align-items-end">
                                            <div class="col-md-2 form-group mb-2">
                                                <div class="schedule-day-name">{{ $label }}</div>
                                            </div>
                                            <div class="col-md-2 form-group mb-2">
                                                <label>{{ __('Status') }}</label>
                                                <select name="days[{{ $dayKey }}][status]" class="form-control" data-schedule-field="days.{{ $dayKey }}.status">
                                                    @foreach($dayStatuses as $statusKey => $statusLabel)
                                                        <option value="{{ $statusKey }}" {{ $selectedStatus === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3 form-group mb-2">
                                                <label>{{ __('Branch') }}</label>
                                                <select name="days[{{ $dayKey }}][branch_id]" class="form-control js-example-basic-single" data-schedule-field="days.{{ $dayKey }}.branch_id">
                                                    <option value="">{{ __('Use Default Branch') }}</option>
                                                    @foreach($branches as $branch)
                                                        <option value="{{ $branch->id }}" {{ (string) $selectedBranch === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 form-group mb-2">
                                                <label>{{ __('Start') }}</label>
                                                <input type="time" name="days[{{ $dayKey }}][start_time]" class="form-control" value="{{ old("days.$dayKey.start_time", $formatTimeInput($day->start_time ?? '')) }}" data-schedule-field="days.{{ $dayKey }}.start_time">
                                            </div>
                                            <div class="col-md-2 form-group mb-2">
                                                <label>{{ __('End') }}</label>
                                                <input type="time" name="days[{{ $dayKey }}][end_time]" class="form-control" value="{{ old("days.$dayKey.end_time", $formatTimeInput($day->end_time ?? '')) }}" data-schedule-field="days.{{ $dayKey }}.end_time">
                                            </div>
                                            <div class="col-md-1 form-group mb-2">
                                                <label>{{ __('Notes') }}</label>
                                                <input type="text" name="days[{{ $dayKey }}][notes]" class="form-control" value="{{ old("days.$dayKey.notes", $day->notes ?? '') }}" maxlength="255" data-schedule-field="days.{{ $dayKey }}.notes">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="col-md-12 form-group mb-3">
                                    <label>{{ __('Schedule Notes') }}</label>
                                    <textarea name="schedule_notes" class="form-control" rows="3" maxlength="255">{{ old('schedule_notes', $schedule->schedule_notes ?? '') }}</textarea>
                                </div>
                            @else
                                @php($weekRows = old('weeks', [['year' => $defaultYear, 'week_number' => $defaultWeekNumber]]))
                                <div class="col-md-12">
                                    <div class="mb-2">
                                        <h5 class="mb-0">{{ __('Week Schedules') }}</h5>
                                        <div class="small text-muted">{{ __('Daily branch and time fields can be left blank to use the weekly defaults.') }}</div>
                                    </div>

                                    <div id="week-blocks-wrapper">
                                        @foreach($weekRows as $index => $week)
                                            <div class="schedule-week-block border rounded p-3 mb-3" data-week-block>
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <strong>{{ __('Week Schedule') }}</strong>
                                                    <button type="button" class="btn btn-custom-default btn-sm remove-week-block" {{ count($weekRows) <= 1 ? 'disabled' : '' }}>
                                                        <i class="icon-trash"></i>
                                                    </button>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-3 form-group mb-3">
                                                        <label>{{ __('Year') }}</label>
                                                        <input type="number" name="weeks[{{ $index }}][year]" class="form-control schedule-year" value="{{ $week['year'] ?? $defaultYear }}" min="2000" max="2100" required>
                                                    </div>

                                                    <div class="col-md-3 form-group mb-3">
                                                        <label>{{ __('Week Number') }}</label>
                                                        <input type="number" name="weeks[{{ $index }}][week_number]" class="form-control schedule-week-number" value="{{ $week['week_number'] ?? $defaultWeekNumber }}" min="1" max="53" required>
                                                    </div>

                                                    <div class="col-md-6 form-group mb-3">
                                                        <label>{{ __('Week Date Range') }}</label>
                                                        <input type="text" class="form-control schedule-week-range" value="" readonly>
                                                    </div>

                                                    @foreach($dayFields as $dayKey => $label)
                                                        @php($day = $week['days'][$dayKey] ?? [])
                                                        @php($selectedStatus = $day['status'] ?? ($loop->index < 5 ? 'working' : 'rest_day'))
                                                        <div class="col-md-12 schedule-day-row" data-day-row>
                                                            <div class="row align-items-end">
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <div class="schedule-day-name">{{ $label }}</div>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label>{{ __('Status') }}</label>
                                                                    <select name="weeks[{{ $index }}][days][{{ $dayKey }}][status]" class="form-control" data-schedule-field="days.{{ $dayKey }}.status">
                                                                        @foreach($dayStatuses as $statusKey => $statusLabel)
                                                                            <option value="{{ $statusKey }}" {{ $selectedStatus === $statusKey ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-3 form-group mb-2">
                                                                    <label>{{ __('Branch') }}</label>
                                                                    <select name="weeks[{{ $index }}][days][{{ $dayKey }}][branch_id]" class="form-control js-example-basic-single" data-schedule-field="days.{{ $dayKey }}.branch_id">
                                                                        <option value="">{{ __('Use Default Branch') }}</option>
                                                                        @foreach($branches as $branch)
                                                                            <option value="{{ $branch->id }}" {{ (string) ($day['branch_id'] ?? '') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label>{{ __('Start') }}</label>
                                                                    <input type="time" name="weeks[{{ $index }}][days][{{ $dayKey }}][start_time]" class="form-control" value="{{ $day['start_time'] ?? '' }}" placeholder="{{ __('Default') }}" data-schedule-field="days.{{ $dayKey }}.start_time">
                                                                </div>
                                                                <div class="col-md-2 form-group mb-2">
                                                                    <label>{{ __('End') }}</label>
                                                                    <input type="time" name="weeks[{{ $index }}][days][{{ $dayKey }}][end_time]" class="form-control" value="{{ $day['end_time'] ?? '' }}" placeholder="{{ __('Default') }}" data-schedule-field="days.{{ $dayKey }}.end_time">
                                                                </div>
                                                                <div class="col-md-1 form-group mb-2">
                                                                    <label>{{ __('Notes') }}</label>
                                                                    <input type="text" name="weeks[{{ $index }}][days][{{ $dayKey }}][notes]" class="form-control" value="{{ $day['notes'] ?? '' }}" maxlength="255" data-schedule-field="days.{{ $dayKey }}.notes">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach

                                                    <div class="col-md-12 form-group mb-3">
                                                        <label>{{ __('Schedule Notes') }}</label>
                                                        <textarea name="weeks[{{ $index }}][schedule_notes]" class="form-control" rows="2" maxlength="255" data-schedule-field="schedule_notes">{{ $week['schedule_notes'] ?? '' }}</textarea>
                                                    </div>

                                                    @if($index > 0)
                                                        <div class="col-md-12 form-group mb-3">
                                                            <label class="copy-previous-week-label d-inline-flex align-items-center gap-2">
                                                                <input type="checkbox" class="copy-previous-week">
                                                                <span>{{ __('Copy previous week schedule') }}</span>
                                                            </label>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="d-flex justify-content-end mb-3">
                                        <button type="button" id="add-week-block" class="btn btn-custom-default btn-sm">
                                            <i class="icon-plus"></i> {{ __('Add Week') }}
                                        </button>
                                    </div>
                                </div>
                            @endif
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
        var mode = @json($mode);
        var wrapper = document.getElementById('week-blocks-wrapper');
        var addWeekButton = document.getElementById('add-week-block');
        var dayFields = @json(collect($dayFields)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values());
        var dayStatuses = @json(collect($dayStatuses)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values());
        var branches = @json($branches->map(fn ($branch) => ['id' => $branch->id, 'label' => $branch->branch_name.' ('.$branch->branch_code.')'])->values());

        function setupSelect2(scope) {
            if (!$.fn.select2) {
                return;
            }

            $(scope).find('.js-example-basic-single').select2({ width: '100%' });
            $(scope).find('.js-example-basic-multiple').select2({
                width: '100%',
                placeholder: @json(__('Select Employees'))
            });
        }

        setupSelect2(document);

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

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

        function updateWeekRange(block) {
            var yearInput = block.querySelector('.schedule-year');
            var weekInput = block.querySelector('.schedule-week-number');
            var rangeInput = block.querySelector('.schedule-week-range');

            if (!yearInput || !weekInput || !rangeInput) {
                return;
            }

            var year = parseInt(yearInput.value, 10);
            var week = parseInt(weekInput.value, 10);
            if (!year || !week || week < 1 || week > 53) {
                rangeInput.value = '';
                return;
            }

            var start = isoWeekStart(year, week);
            var end = new Date(start);
            end.setDate(start.getDate() + 6);
            rangeInput.value = formatDate(start) + ' - ' + formatDate(end);
        }

        function fieldValue(block, field) {
            var input = block.querySelector('[data-schedule-field="' + field + '"]');
            return input ? input.value : '';
        }

        function setFieldValue(block, field, value) {
            var input = block.querySelector('[data-schedule-field="' + field + '"]');
            if (input) {
                input.value = value;
                if ($(input).hasClass('select2-hidden-accessible')) {
                    $(input).trigger('change');
                }
            }
        }

        function previousWeekBlock(block) {
            if (!wrapper) {
                return null;
            }

            var blocks = Array.prototype.slice.call(wrapper.querySelectorAll('[data-week-block]'));
            var index = blocks.indexOf(block);

            return index > 0 ? blocks[index - 1] : null;
        }

        function copyPreviousWeekSchedule(block) {
            var previous = previousWeekBlock(block);
            if (!previous) {
                return;
            }

            dayFields.forEach(function (day) {
                ['status', 'branch_id', 'start_time', 'end_time', 'notes'].forEach(function (field) {
                    setFieldValue(block, 'days.' + day.key + '.' + field, fieldValue(previous, 'days.' + day.key + '.' + field));
                });
            });

            setFieldValue(block, 'schedule_notes', fieldValue(previous, 'schedule_notes'));
        }

        function setupWeekBlock(block) {
            var yearInput = block.querySelector('.schedule-year');
            var weekInput = block.querySelector('.schedule-week-number');
            var copyCheckbox = block.querySelector('.copy-previous-week');

            [yearInput, weekInput].forEach(function (input) {
                if (input) {
                    input.addEventListener('input', function () {
                        updateWeekRange(block);
                    });
                }
            });

            if (copyCheckbox) {
                copyCheckbox.addEventListener('change', function () {
                    if (copyCheckbox.checked) {
                        copyPreviousWeekSchedule(block);
                    }
                });
            }

            updateWeekRange(block);
        }

        function updateRemoveButtons() {
            if (!wrapper) {
                return;
            }

            var blocks = wrapper.querySelectorAll('[data-week-block]');
            blocks.forEach(function (block) {
                var button = block.querySelector('.remove-week-block');
                if (button) {
                    button.disabled = blocks.length <= 1;
                }
            });
        }

        function nextWeekIndex() {
            return wrapper ? wrapper.querySelectorAll('[data-week-block]').length : 0;
        }

        function suggestedWeekNumber() {
            if (!wrapper) {
                return '';
            }

            var blocks = wrapper.querySelectorAll('[data-week-block]');
            var lastBlock = blocks[blocks.length - 1];
            var lastWeekInput = lastBlock ? lastBlock.querySelector('.schedule-week-number') : null;
            var lastWeek = lastWeekInput ? parseInt(lastWeekInput.value, 10) : 0;

            if (!lastWeek || lastWeek >= 53) {
                return '';
            }

            return lastWeek + 1;
        }

        function suggestedYear() {
            if (!wrapper) {
                return new Date().getFullYear();
            }

            var blocks = wrapper.querySelectorAll('[data-week-block]');
            var lastBlock = blocks[blocks.length - 1];
            var lastYearInput = lastBlock ? lastBlock.querySelector('.schedule-year') : null;

            return lastYearInput && lastYearInput.value ? lastYearInput.value : new Date().getFullYear();
        }

        function statusOptionsHtml(defaultStatus) {
            return dayStatuses.map(function (status) {
                var selected = status.key === defaultStatus ? ' selected' : '';
                return '<option value="' + escapeHtml(status.key) + '"' + selected + '>' + escapeHtml(status.label) + '</option>';
            }).join('');
        }

        function branchOptionsHtml() {
            return '<option value="">{{ __('Use Default Branch') }}</option>' + branches.map(function (branch) {
                return '<option value="' + escapeHtml(branch.id) + '">' + escapeHtml(branch.label) + '</option>';
            }).join('');
        }

        function dayRowsHtml(index) {
            return dayFields.map(function (day, dayIndex) {
                var defaultStatus = dayIndex < 5 ? 'working' : 'rest_day';

                return `
                    <div class="col-md-12 schedule-day-row" data-day-row>
                        <div class="row align-items-end">
                            <div class="col-md-2 form-group mb-2">
                                <div class="schedule-day-name">${escapeHtml(day.label)}</div>
                            </div>
                            <div class="col-md-2 form-group mb-2">
                                <label>{{ __('Status') }}</label>
                                <select name="weeks[${index}][days][${escapeHtml(day.key)}][status]" class="form-control" data-schedule-field="days.${escapeHtml(day.key)}.status">
                                    ${statusOptionsHtml(defaultStatus)}
                                </select>
                            </div>
                            <div class="col-md-3 form-group mb-2">
                                <label>{{ __('Branch') }}</label>
                                <select name="weeks[${index}][days][${escapeHtml(day.key)}][branch_id]" class="form-control js-example-basic-single" data-schedule-field="days.${escapeHtml(day.key)}.branch_id">
                                    ${branchOptionsHtml()}
                                </select>
                            </div>
                            <div class="col-md-2 form-group mb-2">
                                <label>{{ __('Start') }}</label>
                                <input type="time" name="weeks[${index}][days][${escapeHtml(day.key)}][start_time]" class="form-control" placeholder="{{ __('Default') }}" data-schedule-field="days.${escapeHtml(day.key)}.start_time">
                            </div>
                            <div class="col-md-2 form-group mb-2">
                                <label>{{ __('End') }}</label>
                                <input type="time" name="weeks[${index}][days][${escapeHtml(day.key)}][end_time]" class="form-control" placeholder="{{ __('Default') }}" data-schedule-field="days.${escapeHtml(day.key)}.end_time">
                            </div>
                            <div class="col-md-1 form-group mb-2">
                                <label>{{ __('Notes') }}</label>
                                <input type="text" name="weeks[${index}][days][${escapeHtml(day.key)}][notes]" class="form-control" maxlength="255" data-schedule-field="days.${escapeHtml(day.key)}.notes">
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function createWeekBlock(index) {
            var block = document.createElement('div');
            block.className = 'schedule-week-block border rounded p-3 mb-3';
            block.setAttribute('data-week-block', '');

            block.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <strong>{{ __('Week Schedule') }}</strong>
                    <button type="button" class="btn btn-custom-default btn-sm remove-week-block">
                        <i class="icon-trash"></i>
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group mb-3">
                        <label>{{ __('Year') }}</label>
                        <input type="number" name="weeks[${index}][year]" class="form-control schedule-year" value="${escapeHtml(suggestedYear())}" min="2000" max="2100" required>
                    </div>

                    <div class="col-md-3 form-group mb-3">
                        <label>{{ __('Week Number') }}</label>
                        <input type="number" name="weeks[${index}][week_number]" class="form-control schedule-week-number" value="${escapeHtml(suggestedWeekNumber())}" min="1" max="53" required>
                    </div>

                    <div class="col-md-6 form-group mb-3">
                        <label>{{ __('Week Date Range') }}</label>
                        <input type="text" class="form-control schedule-week-range" value="" readonly>
                    </div>

                    ${dayRowsHtml(index)}

                    <div class="col-md-12 form-group mb-3">
                        <label>{{ __('Schedule Notes') }}</label>
                        <textarea name="weeks[${index}][schedule_notes]" class="form-control" rows="2" maxlength="255" data-schedule-field="schedule_notes"></textarea>
                    </div>

                    <div class="col-md-12 form-group mb-3">
                        <label class="copy-previous-week-label d-inline-flex align-items-center gap-2">
                            <input type="checkbox" class="copy-previous-week">
                            <span>{{ __('Copy previous week schedule') }}</span>
                        </label>
                    </div>
                </div>
            `;

            return block;
        }

        document.querySelectorAll('[data-week-block]').forEach(function (block) {
            setupWeekBlock(block);
        });

        if (mode === 'edit') {
            setupWeekBlock(document);
        }

        if (addWeekButton && wrapper) {
            addWeekButton.addEventListener('click', function () {
                var block = createWeekBlock(nextWeekIndex());
                wrapper.appendChild(block);
                setupSelect2(block);
                setupWeekBlock(block);
                updateRemoveButtons();
            });

            wrapper.addEventListener('click', function (event) {
                var button = event.target.closest('.remove-week-block');
                if (!button) {
                    return;
                }

                var block = button.closest('[data-week-block]');
                if (block && wrapper.querySelectorAll('[data-week-block]').length > 1) {
                    $(block).find('.js-example-basic-single').select2('destroy');
                    block.remove();
                    updateRemoveButtons();
                }
            });

            updateRemoveButtons();
        }
    })();
</script>
@endpush
