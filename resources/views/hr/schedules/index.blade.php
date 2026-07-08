@extends('layouts.backend')

@push('styles')
<style>
    .schedule-view-switch {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .schedule-filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 10px;
        overflow: visible;
    }

    .schedule-filter-form .schedule-filter-field {
        flex: 1 1 180px;
        min-width: 0;
    }

    .schedule-filter-form .schedule-filter-search {
        flex-basis: 240px;
    }

    .schedule-filter-form .schedule-filter-week,
    .schedule-filter-form .schedule-filter-size {
        flex: 0 0 100px;
    }

    .schedule-filter-form .schedule-filter-month {
        flex: 0 1 180px;
    }

    .schedule-filter-form .schedule-filter-actions {
        display: flex;
        flex: 0 0 auto;
        gap: 8px;
        white-space: nowrap;
    }

    .schedule-filter-form .select2-container {
        width: 100% !important;
        max-width: 100%;
    }

    .select2-container--open {
        z-index: 1060;
    }

    .schedule-list-table {
        font-family: "Arial", "Helvetica Neue", Helvetica, sans-serif;
        font-size: 12px;
    }

    .schedule-list-table th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .schedule-list-table td {
        line-height: 1.35;
    }

    .schedule-list-branch-pill {
        border-left: 3px solid var(--schedule-branch-color, #2f80ed);
        background: var(--schedule-branch-bg, #eef5ff);
        border-radius: 4px;
        color: var(--schedule-branch-text, #1f2937);
        display: inline-block;
        font-size: 11px;
        margin-top: 4px;
        padding: 2px 6px;
    }

    .schedule-list-table tbody tr:nth-child(odd) td {
        background-color: #ffffff;
    }

    .schedule-list-table tbody tr:nth-child(even) td {
        background-color: #eef4fb;
    }

    .schedule-list-table tbody tr:hover td {
        background-color: #dfeeff;
    }

    .schedule-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(160px, 1fr));
        border: 1px solid #e5e7eb;
        border-right: 0;
        border-bottom: 0;
    }

    .schedule-calendar-heading,
    .schedule-calendar-day {
        border-right: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px;
    }

    .schedule-calendar-heading {
        background: #f8f9fa;
        font-weight: 600;
        text-align: center;
    }

    .schedule-calendar-day {
        min-height: 150px;
        background: #fff;
    }

    .schedule-calendar-day.is-muted {
        background: #f8f9fa;
        color: #6c757d;
    }

    .schedule-calendar-date {
        font-weight: 600;
        margin-bottom: 8px;
    }

    .schedule-calendar-toggle {
        border: 0;
        background: transparent;
        color: #2f80ed;
        cursor: pointer;
        font-size: 12px;
        padding: 0;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .schedule-calendar-extra-entries {
        display: none;
        margin-top: 8px;
    }

    .schedule-calendar-day.is-expanded .schedule-calendar-extra-entries {
        display: block;
    }

    .schedule-calendar-entry {
        border-left: 3px solid var(--schedule-branch-color, #2f80ed);
        background: var(--schedule-branch-bg, #eef5ff);
        border-radius: 6px;
        color: var(--schedule-branch-text, #1f2937);
        padding: 6px 8px;
        margin-bottom: 6px;
        font-size: 12px;
    }

    @media (max-width: 991.98px) {
        .schedule-calendar-grid {
            grid-template-columns: 1fr;
        }

        .schedule-calendar-heading {
            display: none;
        }
    }
</style>
@endpush

@section('content')
@php
    $formatScheduleTimeRange = static function (?string $startTime, ?string $endTime = null): string {
        if ($startTime === null || trim($startTime) === '') {
            return '-';
        }

        try {
            $start = \Carbon\CarbonImmutable::createFromFormat('H:i', substr($startTime, 0, 5));
            $end = $endTime !== null && trim($endTime) !== ''
                ? \Carbon\CarbonImmutable::createFromFormat('H:i', substr($endTime, 0, 5))
                : $start->addHours(9);
        } catch (\Throwable) {
            return $startTime;
        }

        if (! $start || ! $end) {
            return $startTime;
        }

        return $start->format('g:i A').' - '.$end->format('g:i A');
    };
    $branchCalendarColors = static function ($branch): array {
        $branchName = (string) ($branch?->branch_name ?? '');
        $branchCode = (string) ($branch?->branch_code ?? '');
        $normalizedName = strtolower($branchName);
        $normalizedCode = strtolower($branchCode);

        if (str_contains($normalizedName, 'main') || $normalizedCode === '00') {
            return ['color' => '#2f80ed', 'background' => '#eef5ff', 'text' => '#1f2937'];
        }

        if (str_contains($normalizedName, 'makati')) {
            return ['color' => '#dc2626', 'background' => '#fee2e2', 'text' => '#7f1d1d'];
        }

        if (str_contains($normalizedName, 'manila')) {
            return ['color' => '#d97706', 'background' => '#fef3c7', 'text' => '#78350f'];
        }

        if (str_contains($normalizedName, 'pampanga')) {
            return ['color' => '#4169e1', 'background' => '#e8edff', 'text' => '#1e3a8a'];
        }

        $palette = [
            ['color' => '#059669', 'background' => '#d1fae5', 'text' => '#064e3b'],
            ['color' => '#7c3aed', 'background' => '#ede9fe', 'text' => '#4c1d95'],
            ['color' => '#0891b2', 'background' => '#cffafe', 'text' => '#164e63'],
            ['color' => '#be123c', 'background' => '#ffe4e6', 'text' => '#881337'],
            ['color' => '#9333ea', 'background' => '#f3e8ff', 'text' => '#581c87'],
            ['color' => '#0f766e', 'background' => '#ccfbf1', 'text' => '#134e4a'],
            ['color' => '#c2410c', 'background' => '#ffedd5', 'text' => '#7c2d12'],
        ];
        $index = $branch?->id ? ((int) $branch->id % count($palette)) : 0;

        return $palette[$index];
    };
    $formatScheduleDay = static function ($day, $defaultBranch) use ($formatScheduleTimeRange, $branchCalendarColors): string {
        if (! $day) {
            return '-';
        }

        if ($day->status !== 'working') {
            return e(\Illuminate\Support\Str::headline((string) $day->status));
        }

        $branch = $day->branch ?? $defaultBranch;
        $time = e($formatScheduleTimeRange($day->start_time, $day->end_time));

        if (! $branch?->branch_name) {
            return $time;
        }

        $colors = $branchCalendarColors($branch);
        $style = sprintf(
            '--schedule-branch-color: %s; --schedule-branch-bg: %s; --schedule-branch-text: %s;',
            $colors['color'],
            $colors['background'],
            $colors['text']
        );

        return $time.'<div><span class="schedule-list-branch-pill" style="'.$style.'">'.e($branch->branch_name).'</span></div>';
    };
@endphp
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-calendar"></i> {{ __('Schedules') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            @if(auth()->user()?->hasPermission('schedule.create'))
                <a href="{{ route('schedule.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Schedule') }}</a>
            @endif
            <a href="{{ route('schedule.export-excel', request()->query()) }}" class="btn btn-custom-default">
                <i class="icon-cloud-download"></i> {{ __('Export Excel') }}
            </a>
            <a href="{{ route('schedule.export-pdf', array_merge(request()->query(), ['view' => 'calendar'])) }}" class="btn btn-custom-default">
                <i class="icon-printer"></i> {{ __('Export PDF') }}
            </a>
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <div class="schedule-view-switch mb-3">
                        <a href="{{ route('schedule.index', array_merge(request()->except(['page', 'view']), ['view' => 'list'])) }}" class="btn {{ $filters['view'] === 'list' ? 'btn-custom' : 'btn-custom-default' }}">
                            <i class="icon-list"></i> {{ __('List View') }}
                        </a>
                        <a href="{{ route('schedule.index', array_merge(request()->except(['page', 'view']), ['view' => 'calendar'])) }}" class="btn {{ $filters['view'] === 'calendar' ? 'btn-custom' : 'btn-custom-default' }}">
                            <i class="icon-calendar"></i> {{ __('Calendar View') }}
                        </a>
                    </div>

                    <form method="GET" class="schedule-filter-form mb-3">
                        <input type="hidden" name="view" value="{{ $filters['view'] }}">
                        <div class="schedule-filter-field schedule-filter-search">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search code/employee/branch') }}">
                        </div>
                        <div class="schedule-filter-field">
                            <select name="employee_id" class="form-control js-example-basic-single">
                                <option value="">{{ __('All Employees') }}</option>
                                @foreach($employees as $employee)
                                    @php($employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    <option value="{{ $employee->id }}" {{ (string) $filters['employee_id'] === (string) $employee->id ? 'selected' : '' }}>{{ $employeeName }} ({{ $employee->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="schedule-filter-field">
                            <select name="branch_id" class="form-control js-example-basic-single">
                                <option value="">{{ __('All Branches') }}</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) $filters['branch_id'] === (string) $branch->id ? 'selected' : '' }}>{{ $branch->branch_name }} ({{ $branch->branch_code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="schedule-filter-field schedule-filter-week">
                            <input type="number" name="week_number" value="{{ $filters['week_number'] }}" class="form-control" min="1" max="53" placeholder="{{ __('Week') }}">
                        </div>
                        <div class="schedule-filter-field schedule-filter-week">
                            <input type="number" name="year" value="{{ $filters['year'] }}" class="form-control" min="2000" max="2100" placeholder="{{ __('Year') }}">
                        </div>
                        @if($filters['view'] === 'calendar')
                            <div class="schedule-filter-field schedule-filter-month">
                                <input type="month" name="calendar_month" value="{{ $filters['calendar_month'] }}" class="form-control">
                            </div>
                        @endif
                        <div class="schedule-filter-field schedule-filter-size">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="schedule-filter-actions">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('schedule.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    @if($filters['view'] === 'calendar')
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="{{ route('schedule.index', array_merge(request()->except(['page', 'calendar_month']), ['view' => 'calendar', 'calendar_month' => $calendar['previous_month']])) }}" class="btn btn-custom-default btn-sm">
                                <i class="icon-arrow-left"></i> {{ __('Previous') }}
                            </a>
                            <h4 class="mb-0">{{ $calendar['month']->format('F Y') }}</h4>
                            <a href="{{ route('schedule.index', array_merge(request()->except(['page', 'calendar_month']), ['view' => 'calendar', 'calendar_month' => $calendar['next_month']])) }}" class="btn btn-custom-default btn-sm">
                                {{ __('Next') }} <i class="icon-arrow-right"></i>
                            </a>
                        </div>

                        <div class="schedule-calendar-grid">
                            @foreach([__('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday'), __('Sunday')] as $weekday)
                                <div class="schedule-calendar-heading">{{ $weekday }}</div>
                            @endforeach

                            @foreach($calendar['days'] as $day)
                                <div class="schedule-calendar-day {{ $day['is_current_month'] ? '' : 'is-muted' }}">
                                    <div class="schedule-calendar-date">{{ $day['date']->format('M d') }}</div>

                                    @if(count($day['entries']) > 0)
                                        @foreach(array_slice($day['entries'], 0, 3) as $entry)
                                            <div
                                                class="schedule-calendar-entry"
                                                style="--schedule-branch-color: {{ $entry['branch_color'] }}; --schedule-branch-bg: {{ $entry['branch_background'] }}; --schedule-branch-text: {{ $entry['branch_text_color'] }};"
                                            >
                                                <strong>{{ $entry['time'] }}</strong>
                                                <div>{{ $entry['employee_name'] }} @if($entry['employee_code'])({{ $entry['employee_code'] }})@endif</div>
                                                <div>{{ $entry['branch_name'] }} @if($entry['branch_code'])({{ $entry['branch_code'] }})@endif</div>
                                            </div>
                                        @endforeach

                                        @if(count($day['entries']) > 3)
                                            <div class="schedule-calendar-extra-entries">
                                                @foreach(array_slice($day['entries'], 3) as $entry)
                                                    <div
                                                        class="schedule-calendar-entry"
                                                        style="--schedule-branch-color: {{ $entry['branch_color'] }}; --schedule-branch-bg: {{ $entry['branch_background'] }}; --schedule-branch-text: {{ $entry['branch_text_color'] }};"
                                                    >
                                                        <strong>{{ $entry['time'] }}</strong>
                                                        <div>{{ $entry['employee_name'] }} @if($entry['employee_code'])({{ $entry['employee_code'] }})@endif</div>
                                                        <div>{{ $entry['branch_name'] }} @if($entry['branch_code'])({{ $entry['branch_code'] }})@endif</div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <button type="button" class="schedule-calendar-toggle" data-calendar-toggle data-hidden-count="{{ count($day['entries']) - 3 }}">
                                                {{ __('Show more schedules') }} ({{ count($day['entries']) - 3 }})
                                            </button>
                                        @endif
                                    @else
                                        <div class="small text-muted">{{ __('No schedules') }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle schedule-list-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Code') }}</th>
                                        <th>{{ __('Employee') }}</th>
                                        <th>{{ __('Year/Week') }}</th>
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
                                        @php($weekStart = \Carbon\CarbonImmutable::now()->setISODate((int) ($schedule->year ?? now()->year), (int) $schedule->week_number))
                                        @php($weekEnd = $weekStart->addDays(6))
                                        @php($daysByName = $schedule->days->keyBy('day_of_week'))
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
                                            <td>
                                                {{ $schedule->year ?? now()->year }} / {{ $schedule->week_number }}
                                                <div class="small text-muted">{{ $weekStart->format('M d') }} - {{ $weekEnd->format('M d, Y') }}</div>
                                            </td>
                                            <td>{!! $formatScheduleDay($daysByName->get('monday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('tuesday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('wednesday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('thursday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('friday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('saturday'), $schedule->defaultBranch) !!}</td>
                                            <td>{!! $formatScheduleDay($daysByName->get('sunday'), $schedule->defaultBranch) !!}</td>
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
                                            <td colspan="11" class="text-center">{{ __('No schedules found.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{ $schedules->links('pagination::bootstrap-5') }}
                    @endif
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

        document.querySelectorAll('[data-calendar-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var day = button.closest('.schedule-calendar-day');
                if (!day) {
                    return;
                }

                var expanded = day.classList.toggle('is-expanded');
                var count = button.getAttribute('data-hidden-count') || '';
                button.textContent = expanded
                    ? @json(__('Show fewer schedules'))
                    : @json(__('Show more schedules')) + (count ? ' (' + count + ')' : '');
            });
        });
    })();
</script>
@endpush
