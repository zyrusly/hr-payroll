<?php

namespace App\Modules\Schedules\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Modules\Schedules\Http\Requests\StoreScheduleRequest;
use App\Modules\Schedules\Http\Requests\UpdateScheduleRequest;
use App\Modules\Schedules\Repositories\ScheduleRepository;
use App\Modules\Schedules\Services\ScheduleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly ScheduleService $scheduleService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);
        $calendarMonth = $this->resolveCalendarMonth($filters['calendar_month']);

        return view('hr.schedules.index', [
            'schedules' => $this->scheduleRepository->paginate($filters),
            'calendar' => $this->buildCalendar($filters, $calendarMonth),
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'filters' => $filters,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $calendarMonth = $request->filled('calendar_month') || $filters['view'] === 'calendar'
            ? $this->resolveCalendarMonth($filters['calendar_month'])
            : null;
        $schedules = $this->scheduleRepository->listForExport($filters, $calendarMonth);
        $fileName = 'schedule_export_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($schedules, $filters): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Schedule Export', '', '', '', '', '', '', '']);
            fputcsv($output, ['Name', 'Branch', 'Day', 'Shift', 'Attendance In/Out', 'Date', 'Changes this week', '']);

            foreach ($schedules as $schedule) {
                $employeeName = trim(($schedule->employee?->first_name ?? '').' '.($schedule->employee?->last_name ?? '')) ?: 'Employee';

                foreach ($schedule->days as $day) {
                    if ((string) $day->status !== 'working' || $this->dayExcludedByBranchFilter($day, $filters)) {
                        continue;
                    }

                    $branch = $day->branch ?? $schedule->defaultBranch;

                    fputcsv($output, [
                        $employeeName,
                        $branch?->branch_name ?? '',
                        CarbonImmutable::parse($day->work_date)->format('l'),
                        $this->formatExportShift($day->start_time, $day->end_time),
                        '',
                        CarbonImmutable::parse($day->work_date)->format('Y-m-d'),
                        '',
                        '',
                    ]);
                }
            }

            fclose($output);
        };

        return response()->stream($callback, Response::HTTP_OK, $headers);
    }

    public function exportPdf(Request $request): Response
    {
        $filters = $this->resolveFilters($request);
        $calendarMonth = $this->resolveCalendarMonth($filters['calendar_month']);
        $calendar = $this->buildCalendar($filters, $calendarMonth);
        $fileName = 'schedule_calendar_'.$calendarMonth->format('Y_m').'.pdf';

        return Pdf::loadView('hr.schedules.exports.calendar-pdf', [
            'calendar' => $calendar,
            'filters' => $filters,
        ])
            ->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    public function create(): View
    {
        return view('hr.schedules.form', [
            'mode' => 'create',
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'defaultWeekNumber' => (int) now()->isoWeek(),
            'defaultYear' => (int) now()->year,
        ]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $createdCount = $this->scheduleService->createSchedules($request->validated());

        return redirect()
            ->route('schedule.index')
            ->with('success', trans_choice('{1} Schedule created successfully.|[2,*] :count schedules created successfully.', $createdCount, ['count' => $createdCount]));
    }

    public function edit(Schedule $schedule): View
    {
        $schedule->load(['days.branch', 'defaultBranch']);

        return view('hr.schedules.form', [
            'mode' => 'edit',
            'schedule' => $schedule,
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'defaultWeekNumber' => (int) now()->isoWeek(),
            'defaultYear' => (int) now()->year,
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->scheduleService->updateSchedule($schedule, $request->validated());

        return redirect()->route('schedule.index')->with('success', __('Schedule updated successfully.'));
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->scheduleService->deleteSchedule($schedule);

        return redirect()->route('schedule.index')->with('success', __('Schedule deleted successfully.'));
    }

    /**
     * @return array{month: CarbonImmutable, previous_month: string, next_month: string, days: array<int, array{date: CarbonImmutable, is_current_month: bool, entries: array<int, array{time: string, employee_name: string, employee_code: string, branch_name: string, branch_code: string, branch_color: string, branch_background: string, branch_text_color: string, schedule_id: int}>}>}
     */
    private function buildCalendar(array $filters, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);
        $entriesByDate = [];
        $fallbackBranchColors = [];
        $fallbackColorIndex = 0;
        $schedules = $this->scheduleRepository->listForCalendar($filters, $month);

        foreach ($schedules as $schedule) {
            foreach ($schedule->days as $day) {
                if ((string) $day->status !== 'working') {
                    continue;
                }

                if ($this->dayExcludedByBranchFilter($day, $filters)) {
                    continue;
                }

                $time = $this->formatScheduleTimeRange($day->start_time, $day->end_time);
                if ($time === null) {
                    continue;
                }

                $date = CarbonImmutable::parse($day->work_date);
                if ($date->lt($start) || $date->gt($end)) {
                    continue;
                }

                $key = $date->format('Y-m-d');
                $branch = $day->branch ?? $schedule->defaultBranch;
                $branchColors = $this->branchCalendarColors(
                    (int) ($branch?->id ?? 0),
                    (string) ($branch?->branch_name ?? ''),
                    (string) ($branch?->branch_code ?? ''),
                    $fallbackBranchColors,
                    $fallbackColorIndex
                );
                $entriesByDate[$key] ??= [];
                $entriesByDate[$key][] = [
                    'time' => $time,
                    'employee_name' => trim(($schedule->employee?->first_name ?? '').' '.($schedule->employee?->last_name ?? '')) ?: __('Employee'),
                    'employee_code' => (string) ($schedule->employee?->employee_code ?? ''),
                    'branch_name' => (string) ($branch?->branch_name ?? __('Branch')),
                    'branch_code' => (string) ($branch?->branch_code ?? ''),
                    'branch_color' => $branchColors['color'],
                    'branch_background' => $branchColors['background'],
                    'branch_text_color' => $branchColors['text'],
                    'schedule_id' => (int) $schedule->id,
                ];
            }
        }

        foreach ($entriesByDate as $key => $entries) {
            usort($entries, fn (array $a, array $b): int => [$a['time'], $a['employee_name']] <=> [$b['time'], $b['employee_name']]);
            $entriesByDate[$key] = $entries;
        }

        $days = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $days[] = [
                'date' => $date,
                'is_current_month' => $date->month === $month->month,
                'entries' => $entriesByDate[$date->format('Y-m-d')] ?? [],
            ];
        }

        return [
            'month' => $month,
            'previous_month' => $month->subMonthNoOverflow()->format('Y-m'),
            'next_month' => $month->addMonthNoOverflow()->format('Y-m'),
            'days' => $days,
        ];
    }

    /**
     * @return array{q: string, branch_id: string, employee_id: string, year: string, week_number: string, view: string, calendar_month: string, per_page: int}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q')),
            'branch_id' => (string) $request->input('branch_id', ''),
            'employee_id' => (string) $request->input('employee_id', ''),
            'year' => (string) $request->input('year', ''),
            'week_number' => (string) $request->input('week_number', ''),
            'view' => in_array($request->input('view'), ['list', 'calendar'], true) ? (string) $request->input('view') : 'list',
            'calendar_month' => $this->resolveCalendarMonth((string) $request->input('calendar_month', now()->format('Y-m')))->format('Y-m'),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function dayExcludedByBranchFilter($day, array $filters): bool
    {
        $branchId = (string) ($filters['branch_id'] ?? '');

        return $branchId !== '' && (string) ($day->branch_id ?? '') !== $branchId;
    }

    /**
     * @param array<string, array{color: string, background: string, text: string}> $fallbackBranchColors
     * @return array{color: string, background: string, text: string}
     */
    private function branchCalendarColors(
        int $branchId,
        string $branchName,
        string $branchCode,
        array &$fallbackBranchColors,
        int &$fallbackColorIndex
    ): array {
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

        $key = $branchId > 0 ? 'id:'.$branchId : 'branch:'.strtolower(trim($branchName.'|'.$branchCode));
        if (! isset($fallbackBranchColors[$key])) {
            $palette = [
                ['color' => '#059669', 'background' => '#d1fae5', 'text' => '#064e3b'],
                ['color' => '#7c3aed', 'background' => '#ede9fe', 'text' => '#4c1d95'],
                ['color' => '#0891b2', 'background' => '#cffafe', 'text' => '#164e63'],
                ['color' => '#be123c', 'background' => '#ffe4e6', 'text' => '#881337'],
                ['color' => '#9333ea', 'background' => '#f3e8ff', 'text' => '#581c87'],
                ['color' => '#0f766e', 'background' => '#ccfbf1', 'text' => '#134e4a'],
                ['color' => '#c2410c', 'background' => '#ffedd5', 'text' => '#7c2d12'],
            ];

            $fallbackBranchColors[$key] = $palette[$fallbackColorIndex % count($palette)];
            $fallbackColorIndex++;
        }

        return $fallbackBranchColors[$key];
    }

    private function resolveCalendarMonth(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value.'-01') ?: CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    private function formatScheduleTimeRange(?string $startTime, ?string $endTime): ?string
    {
        if ($startTime === null || trim($startTime) === '') {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('H:i', substr($startTime, 0, 5));
            $end = $endTime !== null && trim($endTime) !== ''
                ? CarbonImmutable::createFromFormat('H:i', substr($endTime, 0, 5))
                : $start->addHours(9);
        } catch (\Throwable) {
            return $startTime;
        }

        if (! $start || ! $end) {
            return $startTime;
        }

        return $start->format('g:i A').' - '.$end->format('g:i A');
    }

    private function formatExportShift(?string $startTime, ?string $endTime): string
    {
        if ($startTime === null || trim($startTime) === '') {
            return '';
        }

        try {
            $start = CarbonImmutable::createFromFormat('H:i', substr($startTime, 0, 5));
            $end = $endTime !== null && trim($endTime) !== ''
                ? CarbonImmutable::createFromFormat('H:i', substr($endTime, 0, 5))
                : $start->addHours(9);
        } catch (\Throwable) {
            return (string) $startTime;
        }

        return $this->formatExportTime($start).' to '.$this->formatExportTime($end);
    }

    private function formatExportTime(CarbonImmutable $time): string
    {
        return $time->minute === 0 ? $time->format('g A') : $time->format('g:i A');
    }
}
