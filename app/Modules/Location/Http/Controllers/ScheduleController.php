<?php

namespace App\Modules\Location\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Modules\Location\Http\Requests\StoreScheduleRequest;
use App\Modules\Location\Http\Requests\UpdateScheduleRequest;
use App\Modules\Location\Repositories\ScheduleRepository;
use App\Modules\Location\Services\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly ScheduleService $scheduleService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'branch_id' => (string) $request->input('branch_id', ''),
            'employee_id' => (string) $request->input('employee_id', ''),
            'week_number' => (string) $request->input('week_number', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.schedules.index', [
            'schedules' => $this->scheduleRepository->paginate($filters),
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.schedules.form', [
            'mode' => 'create',
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'defaultWeekNumber' => (int) now()->isoWeek(),
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
        return view('hr.schedules.form', [
            'mode' => 'edit',
            'schedule' => $schedule,
            'branches' => $this->scheduleRepository->listBranches(),
            'employees' => $this->scheduleRepository->listActiveEmployees(),
            'defaultWeekNumber' => (int) now()->isoWeek(),
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
}
